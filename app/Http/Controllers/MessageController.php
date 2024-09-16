<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    // Send a new message
    public function send(Request $request)
    {
        $request->validate([
            'sender_id' => 'required|integer',
            'receiver_id' => 'required|integer',
            'message' => 'required|string',
        ]);

        $message = Message::create([
            'sender_id' => $request->sender_id,
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
            'status' => 'sent', // initial status
        ]);

         // Check if the conversation already exists
         $conversation = Conversation::where(function ($query) use ($request) {
            $query->where('user_one_id', $request->sender_id)
                  ->where('user_two_id', $request->receiver_id);
        })->orWhere(function ($query) use ($request) {
            $query->where('user_one_id', $request->receiver_id)
                  ->where('user_two_id', $request->sender_id);
        })->first();

        if($conversation){

            $conversation->last_message= $request->message;
                $conversation->save();
        } else if(!$conversation){
            $create_convo = Conversation::create(
                [
                   'user_one_id' => $request->sender_id,
                'user_two_id' => $request->receiver_id,
                'last_message' => $request->message,
                ]
                );
                $create_convo->save();
            
        }


        // Optionally, you can broadcast the message in real time
        event(new MessageSent($message));

        return response()->json(['message' => 'Message sent successfully', 'data' => $message], 201);
    }

    // Mark message as delivered
    public function markAsDelivered(Request $request)
    {
        $request->validate([
            'message_id' => 'required|integer',
        ]);

        $message = Message::find($request->message_id);

        if (!$message) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        $message->status = 'delivered';
        $message->save();

        // Optionally, you can broadcast this update in real time
        // event(new MessageDelivered($message));

        return response()->json(['message' => 'Message marked as delivered', 'data' => $message], 200);
    }

    // Mark message as seen
    public function markAsSeen(Request $request)
    {
        $request->validate([
            'message_id' => 'required|integer',
        ]);

        $message = Message::find($request->message_id);

        if (!$message) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        $message->status = 'seen';
        $message->save();

        // Optionally, you can broadcast this update in real time
        // event(new MessageSeen($message));

        return response()->json(['message' => 'Message marked as seen', 'data' => $message], 200);
    }

    // Fetch message history between two users
    public function getMessageHistory(Request $request)
    {

        $request->validate([
            'user_id' => 'required|integer',
            'receiver_id' => 'required|integer',

        ]);

        $userID= $request->user_id;
        $receiver_id= $request->receiver_id;
        // $message = Message::where('sender_id', $request->user_id)->whe;
        // Fetch message history between the logged-in user and another user
        $messages = Message::with('receiver')->where(function($query) use ( $userID, $receiver_id) {
            $query->where('sender_id', $userID)
                  ->where('receiver_id',  $receiver_id);
        })->orWhere(function($query) use ($userID, $receiver_id) {
            $query->where('sender_id', $receiver_id)
                  ->where('receiver_id', $userID);
        })->orderBy('created_at', 'asc')->get();

        return response()->json(['data' => $messages], 200);
    }

   
}
