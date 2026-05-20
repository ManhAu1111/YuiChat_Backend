<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserOnlineStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $isOnline;
    public $lastActiveAt;

    /**
     * Create a new event instance.
     */
    public function __construct($userId, $isOnline, $lastActiveAt)
    {
        $this->userId = $userId;
        $this->isOnline = $isOnline;
        $this->lastActiveAt = $lastActiveAt;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('user-status'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'UserOnlineStatusChanged';
    }
}
