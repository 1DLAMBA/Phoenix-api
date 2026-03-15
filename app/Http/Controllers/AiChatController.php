<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use App\Support\SpecializationInference;
use App\Support\HospitalContextBuilder;
use App\Support\TokenCounter;
use App\Support\ConversationSummarizer;
use Illuminate\Support\Facades\Log;

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
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'content' => 'required|string',
                'specialization' => 'nullable|string',
            ]);

            $convo = AiConversation::where('id', $conversationId)
                ->where('user_id', $request->user_id)
                ->firstOrFail();

            $userMessage = $request->input('content');

            // Save the user's message
            AiMessage::create([
                'ai_conversation_id' => $convo->id,
                'role' => 'user',
                'content' => $userMessage,
            ]);

            // Load user with relationships - use closure-based eager loading for hasOne relationships
            $user = User::with([
                'doctors' => function($query) {
                    $query->with('user');
                },
                'nurses' => function($query) {
                    $query->with('user');
                },
                'clients' => function($query) {
                    $query->with('user');
                },
                'otherProfessionals' => function($query) {
                    $query->with('user');
                }
            ])->findOrFail($request->user_id);
            if (!$user instanceof User) {
                throw new \RuntimeException('Failed to load user context');
            }

            // Classify query type for temperature adjustment
            $queryType = SpecializationInference::classifyQueryType($userMessage);
            $temperature = $this->getTemperatureForQueryType($queryType);

            // Build comprehensive context using HospitalContextBuilder
            $contextBuilder = new HospitalContextBuilder();
            $context = $contextBuilder->buildForUser($user, $userMessage, $convo->id);
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
            $allMessages = AiMessage::where('ai_conversation_id', $convo->id)
                ->orderBy('created_at')
                ->get()
                ->map(function($m) {
                    return ['role' => $m->role, 'content' => $m->content];
                })
                ->toArray();
            
            // Check token count and summarize if needed
            $estimatedTokens = TokenCounter::estimateMessages(array_merge($history, $allMessages));
            
            if (TokenCounter::shouldTruncate(array_merge($history, $allMessages), 6000)) {
                Log::info("Conversation {$convo->id} exceeds token limit. Summarizing...", [
                    'estimated_tokens' => $estimatedTokens
                ]);
                
                // Summarize conversation, keeping recent 10 messages
                $summarized = ConversationSummarizer::summarize($allMessages, 10);
                $history = array_merge($history, $summarized);
            } else {
                $history = array_merge($history, $allMessages);
            }

            // Calculate max tokens for response
            $contextTokens = TokenCounter::estimateMessages($history);
            $maxTokens = TokenCounter::getRecommendedMaxTokens($contextTokens);

            // Call Hugging Face Inference Router (OpenAI-compatible)
            $token = env('HF_TOKEN', env('GROQ_API_KEY'));
            
            $response = Http::timeout(30)
                ->withToken($token)
                ->post('https://router.huggingface.co/v1/chat/completions', [
                    'model' => $this->resolveModel($convo->model),
                    'messages' => $history,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]);

            if (!$response->successful()) {
                Log::error('AI API request failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'conversation_id' => $convo->id
                ]);
                
                return response()->json([
                    'error' => 'AI service temporarily unavailable',
                    'details' => $response->json()
                ], $response->status());
            }

            $assistant = data_get($response->json(), 'choices.0.message.content');
            
            if (empty($assistant)) {
                Log::error('Empty response from AI', [
                    'conversation_id' => $convo->id,
                    'response' => $response->json()
                ]);
                
                return response()->json([
                    'error' => 'Received empty response from AI service'
                ], 500);
            }

            // Save assistant reply
            AiMessage::create([
                'ai_conversation_id' => $convo->id,
                'role' => 'assistant',
                'content' => $assistant,
            ]);

            // Update conversation metadata
            $convo->last_message = $assistant;
            
            // Store metadata if columns exist (migration may not be run yet)
            $tableName = $convo->getTable();
            if (Schema::hasColumn($tableName, 'token_count')) {
                $convo->token_count = $estimatedTokens;
            }
            if (Schema::hasColumn($tableName, 'query_type')) {
                $convo->query_type = $queryType;
            }
            if (Schema::hasColumn($tableName, 'temperature_used')) {
                $convo->temperature_used = $temperature;
            }
            if (Schema::hasColumn($tableName, 'model_version')) {
                $convo->model_version = $this->resolveModel($convo->model);
            }
            
            $convo->save();

            return response()->json([
                'assistant' => $assistant,
                'metadata' => [
                    'query_type' => $queryType,
                    'temperature' => $temperature,
                    'estimated_tokens' => $contextTokens,
                    'max_tokens' => $maxTokens,
                ],
                'raw' => $response->json()
            ]);
            
        } catch (\Exception $e) {
            Log::error('AI request exception', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to process AI request',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get temperature based on query type
     */
    private function getTemperatureForQueryType(string $queryType): float
    {
        return match($queryType) {
            'appointment' => 0.3,  // Very precise for scheduling
            'assignment' => 0.3,   // Precise for care-team facts
            'notification' => 0.3, // Precise for unread counts and status
            'records' => 0.4,      // Conservative for records
            'medical' => 0.5,      // Conservative for medical advice
            'general' => 0.7,      // Natural for general conversation
            default => 0.7
        };
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

