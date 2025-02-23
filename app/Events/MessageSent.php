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
}
