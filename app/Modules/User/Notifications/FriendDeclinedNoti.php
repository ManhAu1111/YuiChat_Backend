<?php

namespace App\Modules\User\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class FriendDeclinedNoti extends Notification implements ShouldBroadcastNow
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
     * Broadcast and database.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Persisted notification payload (database channel).
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'sender_id' => $this->decliner->id,
            'sender_name' => $this->decliner->name ?? null,
            'avatar' => $this->decliner->avatar ?? null,
            'message' => ($this->decliner->name ?? 'Someone') . ' declined your friend request.',
        ];
    }

    /**
     * Fallback representation.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'sender_id' => $this->decliner->id,
            'sender_name' => $this->decliner->name ?? null,
            'avatar' => $this->decliner->avatar ?? null,
            'message' => ($this->decliner->name ?? 'Someone') . ' declined your friend request.',
        ];
    }

    /**
     * Real-time broadcast payload.
     * The frontend listens for type.endsWith('FriendDeclinedNoti') and removes
     * the decliner's ID from the local pending_sent array.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage([
            'sender_id' => $this->decliner->id,
            'sender_name' => $this->decliner->name ?? null,
            'avatar' => $this->decliner->avatar ?? null,
            'message' => ($this->decliner->name ?? 'Someone') . ' declined your friend request.',
        ]))->onConnection('sync');
    }
}
