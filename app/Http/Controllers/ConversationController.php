<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    // Get all conversations for the logged-in user
    public function getConversations(string $userId)
    {
        // $userId = auth()->id();

        // Fetch conversations where the user is either user_one or user_two
        $conversations = Conversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with(['userOne', 'userTwo']) // Eager load the users and latest message
            ->get();
            

        return response()->json(['data' => $conversations], 200);
    }

    // Create a new conversation (if it doesn't exist)
    public function createConversation(Request $request)
    {
        $request->validate([
            'user_one_id' => 'required|integer',
            'user_two_id' => 'required|integer',
        ]);

        // Check if the conversation already exists
        $conversation = Conversation::where(function ($query) use ($request) {
            $query->where('user_one_id', $request->user_one_id)
                  ->where('user_two_id', $request->user_two_id);
        })->orWhere(function ($query) use ($request) {
            $query->where('user_one_id', $request->user_two_id)
                  ->where('user_two_id', $request->user_one_id);
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $request->user_one_id,
                'user_two_id' => $request->user_two_id,
            ]);
        }

        return response()->json(['message' => 'Conversation created successfully', 'data' => $conversation], 201);
    }
}
