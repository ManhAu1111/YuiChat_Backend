<?php

namespace App\Modules\User\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class FriendAcceptedNoti extends Notification implements ShouldBroadcast
{
    use Queueable;

    public User $acceptor;

    /**
     * The user who clicked "Accept" (the receiver of the original request).
     */
    public function __construct(User $acceptor)
    {
        $this->acceptor = $acceptor;
    }

    /**
     * Delivery channels: save to DB for notification history AND broadcast
     * in real-time so the original sender's FriendshipButton updates instantly.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Persisted notification payload (database channel).
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'sender_id' => $this->acceptor->id,
            'sender_name' => $this->acceptor->name ?? null,
            'avatar' => $this->acceptor->avatar ?? null,
            'message' => ($this->acceptor->name ?? 'Someone') . ' accepted your friend request.',
        ];
    }

    /**
     * Real-time broadcast payload (broadcast channel).
     * The frontend listens for type.endsWith('FriendAcceptedNoti').
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'sender_id' => $this->acceptor->id,
            'message'   => ($this->acceptor->name ?? 'Someone') . ' accepted your friend request.',
        ]);
    }
}
