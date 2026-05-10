<?php

namespace App\Modules\User\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class FriendDeclinedNoti extends Notification implements ShouldBroadcast
{
    use Queueable;

    public User $decliner;

    /**
     * The user who declined or cancelled the friend request.
     */
    public function __construct(User $decliner)
    {
        $this->decliner = $decliner;
    }

    /**
     * Broadcast only — no database record needed for a silent UI blip.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['broadcast'];
    }

    /**
     * Real-time broadcast payload.
     * The frontend listens for type.endsWith('FriendDeclinedNoti') and removes
     * the decliner's ID from the local pending_sent array.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'sender_id' => $this->decliner->id,
        ]);
    }
}
