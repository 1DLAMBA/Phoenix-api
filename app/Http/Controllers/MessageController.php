<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\NotificationSent;
use App\Mail\MessageSentMail;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        $sender = User::findOrFail($request->sender_id);
        
        // Create notification for receiver (in addition to Pusher real-time notification)
        $receiver = User::find($request->receiver_id);
        if ($receiver) {
            $notification = Notification::create([
                'user_id' => $request->receiver_id,
                'type' => 'message',
                'title' => 'New Message from ' . $sender->name,
                'message' => $request->message,
                'related_id' => $message->id,
                'related_type' => 'Message',
            ]);

            // Broadcast the notification in real-time
            try {
                event(new NotificationSent($notification));
            } catch (\Exception $e) {
                Log::error('Failed to broadcast message notification: ' . $e->getMessage());
            }
            
            // Send email notification to receiver
            try {
                if ($receiver->email) {
                    Mail::to($receiver->email)->send(new MessageSentMail($message, $sender, $receiver));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send message email: ' . $e->getMessage());
            }
        }
        
        // DEBUG: Log BEFORE broadcast attempt
        \Log::info('=== MESSAGE SEND DEBUG START ===', [
            'message_id' => $message->id,
            'sender_id' => $message->sender_id,
            'receiver_id' => $message->receiver_id,
            'sender_name' => $sender->name ?? 'NO_NAME'
        ]);
        
        // Broadcast the message in real time
        // Pass the sender's name as a string (as expected by MessageSent event)
        try {
            // Check if event class exists
            if (!class_exists(\App\Events\MessageSent::class)) {
                \Log::error('MessageSent event class does not exist!');
                throw new \Exception('MessageSent event class not found');
            }
            
            $broadcastDriver = config('broadcasting.default');
            $pusherKey = config('broadcasting.connections.pusher.key');
            $pusherAppId = config('broadcasting.connections.pusher.app_id');
            $pusherSecret = config('broadcasting.connections.pusher.secret');
            $pusherCluster = config('broadcasting.connections.pusher.options.cluster');
            
            \Log::info('Broadcasting MessageSent event - PRE EVENT', [
                'message_id' => $message->id,
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'broadcast_driver' => $broadcastDriver,
                'pusher_key' => $pusherKey ? substr($pusherKey, 0, 10) . '...' : 'MISSING',
                'pusher_app_id' => $pusherAppId ?: 'MISSING',
                'pusher_secret' => $pusherSecret ? 'SET' : 'MISSING',
                'pusher_cluster' => $pusherCluster ?: 'MISSING',
                'channel' => 'messaging-channel',
                'event_name' => 'MessageSent',
                'event_class_exists' => class_exists(\App\Events\MessageSent::class)
            ]);
            
            // Create event instance
            $event = new \App\Events\MessageSent($message, $sender->name);
            \Log::info('Event instance created', ['message_id' => $message->id]);
            
            // Fire the event
            event($event);
            
            \Log::info('Event fired - POST EVENT', [
                'message_id' => $message->id,
                'broadcast_driver' => $broadcastDriver
            ]);
            
        } catch (\Exception $e) {
            \Log::error('=== BROADCAST EXCEPTION ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'message_id' => $message->id ?? 'unknown'
            ]);
        } catch (\Throwable $e) {
            \Log::error('=== BROADCAST THROWABLE ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message_id' => $message->id ?? 'unknown'
            ]);
        }
        
        \Log::info('=== MESSAGE SEND DEBUG END ===', ['message_id' => $message->id]);
        
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
