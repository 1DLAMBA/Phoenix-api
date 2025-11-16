<?php
namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels, Dispatchable;

    public $message;
    public $sender_name;

    public function __construct(Message $message, String $sender_name)
    {
        $this->message = $message;
        $this->sender_name = $sender_name;
    }

    public function broadcastOn()
    {
        return new Channel('messaging-channel');
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * Explicitly define the data structure to broadcast
     * This ensures consistent data structure across all environments
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'sender_id' => $this->message->sender_id,
                'receiver_id' => $this->message->receiver_id,
                'message' => $this->message->message,
                'status' => $this->message->status ?? 'sent',
                'created_at' => $this->message->created_at?->toDateTimeString(),
            ],
            'sender_name' => $this->sender_name,
        ];
    }
}
