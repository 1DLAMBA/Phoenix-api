<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Support\SpecializationInference;

class AiChatController extends Controller
{
    // List conversations for a user
    public function index(Request $request)
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);
        $convos = AiConversation::withCount('messages')
            ->where('user_id', $request->user_id)
            ->orderByDesc('updated_at')
            ->get();
        return response()->json(['data' => $convos]);
    }

    // Create a new conversation (optionally with a title/system_prompt)
    public function create(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'title' => 'nullable|string',
            'system_prompt' => 'nullable|string',
            'model' => 'nullable|string',
        ]);
        $convo = AiConversation::create([
            'user_id' => $request->user_id,
            'title' => $request->title,
            'system_prompt' => $request->system_prompt,
            'model' => $request->model ?: 'llama3-70b-8192',
        ]);
        return response()->json(['data' => $convo], 201);
    }

    // Get messages for a conversation
    public function messages(string $conversationId)
    {
        $messages = AiMessage::where('ai_conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get();
        return response()->json(['data' => $messages]);
    }

    // Send a message to Groq using full history
    public function send(Request $request, string $conversationId)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'content' => 'required|string',
            'specialization' => 'nullable|string',
        ]);

        $convo = AiConversation::where('id', $conversationId)
            ->where('user_id', $request->user_id)
            ->firstOrFail();

        // Save the user's message
        AiMessage::create([
            'ai_conversation_id' => $convo->id,
            'role' => 'user',
            'content' => $request->input('content'),
        ]);

        // Build full message history
        $history = [];
        if (!empty($convo->system_prompt)) {
            $history[] = ['role' => 'system', 'content' => $convo->system_prompt];
        }
        foreach (AiMessage::where('ai_conversation_id', $convo->id)->orderBy('created_at')->get() as $m) {
            $history[] = ['role' => $m->role, 'content' => $m->content];
        }

        // Include user context (name, role, and related profile) at the top
        $user = User::with(['doctors', 'nurses', 'clients'])->findOrFail($request->user_id);
        $role = $user->user_type;
        $profile = null;
        switch ($role) {
            case 'doctor':
                $doc = $user->doctors;
                if ($doc) {
                    $profile = [
                        'license_number' => $doc->license_number,
                        'med_school' => $doc->med_school,
                        'specialization' => $doc->specialization,
                        'grad_year' => $doc->grad_year,
                        'availability' => $doc->availability,
                    ];
                }
                break;
            case 'nurse':
                $nurse = $user->nurses;
                if ($nurse) {
                    $profile = [
                        'license_number' => $nurse->license_number,
                        'med_school' => $nurse->med_school,
                        'specialization' => $nurse->specialization,
                        'grad_year' => $nurse->grad_year,
                    ];
                }
                break;
            case 'client':
                $client = $user->clients;
                if ($client) {
                    $profile = [
                        'client_id' => $client->id,
                        'date_of_birth' => $client->date_of_birth,
                        'assigned_doctor_id' => $client->assigned_doctor_id,
                        'assigned_nurse_id' => $client->assigned_nurse_id,
                    ];
                }
                break;
        }

        // Optionally include doctor suggestions based on specialization provided
        $availableDoctors = null;
        $spec = $request->input('specialization');
        if (empty($spec)) {
            $spec = SpecializationInference::infer($request->input('content'));
        }
        if (!empty($spec)) {
            $aliases = \App\Support\SpecializationAliases::aliasesFor($spec);
            $doctors = Doctor::with('user')
                ->where(function ($q) use ($aliases) {
                    foreach ($aliases as $alias) {
                        $q->orWhere('specialization', 'LIKE', '%' . $alias . '%');
                    }
                })
                ->limit(10)
                ->get();
            if ($doctors->count() > 0) {
                $availableDoctors = $doctors->map(fn($d) => [
                    'doctor_id' => $d->id,
                    'name' => optional($d->user)->name,
                    'email' => optional($d->user)->email,
                    'specialization' => $d->specialization,
                    'availability' => $d->availability,
                ])->values();
            }
        }

        $contextObj = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phoneno' => $user->phoneno,
                'gender' => $user->gender,
                'role' => $role,
                'profile' => $profile,
            ],
            'available_doctors' => $availableDoctors,
            'instructions' => 'Personalize responses based on the user context. If available_doctors is present, use it to suggest suitable doctors to the user. Follow safe medical guidelines and avoid making diagnoses without disclaimers.',
        ];


        $context = 'User Context: ' . json_encode($contextObj);
        array_unshift($history, ['role' => 'system', 'content' => $context]);

        // Call Hugging Face Inference Router (OpenAI-compatible)
        $token = env('HF_TOKEN', env('GROQ_API_KEY'));
        $response = Http::withToken($token)
            ->post('https://router.huggingface.co/v1/chat/completions', [
                'model' => $this->resolveModel($convo->model),
                'messages' => $history,
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);

        if (!$response->successful()) {
            return response()->json(['error' => 'Groq request failed', 'details' => $response->json()], $response->status());
        }

        $assistant = data_get($response->json(), 'choices.0.message.content');

        // Save assistant reply
        AiMessage::create([
            'ai_conversation_id' => $convo->id,
            'role' => 'assistant',
            'content' => $assistant,
        ]);

        // Update conversation metadata
        $convo->last_message = $assistant;
        $convo->save();

        return response()->json([
            'assistant' => $assistant,
            'raw' => $response->json()
        ]);
    }

    private function resolveModel(?string $name): string
    {
        $preferred = $name ?: env('HF_DEFAULT_MODEL');
        $map = [
            'llama3-70b-8192' => 'meta-llama/Meta-Llama-3-70B-Instruct',
            'llama3-8b-8192' => 'meta-llama/Meta-Llama-3-8B-Instruct',
            'llama3.1-8b-instant' => 'meta-llama/Llama-3.1-8B-Instruct',
            'llama3.1-70b-versatile' => 'meta-llama/Llama-3.1-70B-Instruct',
        ];
        if ($preferred && isset($map[$preferred])) return $map[$preferred];
        if ($preferred) return $preferred;
        return 'meta-llama/Meta-Llama-3-70B-Instruct';
    }

}

