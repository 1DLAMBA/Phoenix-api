<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Support\SpecializationInference;
use App\Support\HospitalContextBuilder;

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

        // Load user with relationships
        $user = User::with(['doctors', 'nurses', 'clients'])->findOrFail($request->user_id);

        // Build comprehensive context using HospitalContextBuilder
        $contextBuilder = new HospitalContextBuilder();
        $context = $contextBuilder->buildForUser($user, $request->input('content'), $convo->id);
        $systemPrompt = $contextBuilder->formatSystemPrompt($context);

        // Build message history
        $history = [];
        
        // Add custom system prompt if exists, otherwise use generated one
        if (!empty($convo->system_prompt)) {
            $history[] = ['role' => 'system', 'content' => $convo->system_prompt . "\n\n" . $systemPrompt];
        } else {
            $history[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        
        // Add conversation history
        foreach (AiMessage::where('ai_conversation_id', $convo->id)->orderBy('created_at')->get() as $m) {
            $history[] = ['role' => $m->role, 'content' => $m->content];
        }

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

