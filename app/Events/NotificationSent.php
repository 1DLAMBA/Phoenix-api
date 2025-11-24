<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotificationSent implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels, Dispatchable;

    public $notification;

    public function __construct(Notification $notification)
    {
        Log::info('NotificationSent event CONSTRUCTOR called', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'type' => $notification->type
        ]);
        
        $this->notification = $notification;
    }

    public function broadcastOn()
    {
        Log::info('NotificationSent broadcastOn() called', [
            'channel' => 'notifications-channel',
            'notification_id' => $this->notification->id ?? 'unknown',
            'user_id' => $this->notification->user_id ?? 'unknown'
        ]);
        return new Channel('notifications-channel');
    }

    public function broadcastAs(): string
    {
        Log::info('NotificationSent broadcastAs() called', [
            'event_name' => 'NotificationSent',
            'notification_id' => $this->notification->id ?? 'unknown'
        ]);
        return 'NotificationSent';
    }

    /**
     * Explicitly define the data structure to broadcast
     * This ensures consistent data structure across all environments
     */
    public function broadcastWith(): array
    {
        Log::info('NotificationSent broadcastWith() called', [
            'notification_id' => $this->notification->id ?? 'unknown',
            'user_id' => $this->notification->user_id ?? 'unknown'
        ]);
        
        return [
            'notification' => [
                'id' => $this->notification->id,
                'user_id' => $this->notification->user_id,
                'type' => $this->notification->type,
                'title' => $this->notification->title,
                'message' => $this->notification->message,
                'related_id' => $this->notification->related_id,
                'related_type' => $this->notification->related_type,
                'read_at' => $this->notification->read_at?->toDateTimeString(),
                'created_at' => $this->notification->created_at?->toDateTimeString(),
                'updated_at' => $this->notification->updated_at?->toDateTimeString(),
            ],
        ];
    }
}

