<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversation_id;
    public $message_id;
    public $user_id;

    public function __construct($conversationId, $messageId, $userId)
    {
        $this->conversation_id = $conversationId;
        $this->message_id = $messageId;
        $this->user_id = $userId; // The user who read it
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->conversation_id),
        ];
    }
}
