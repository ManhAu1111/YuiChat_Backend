<?php

namespace App\Modules\User\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FriendRequestNoti extends Notification
{
    use Queueable;

    public User $sender;
    public ?string $note;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $sender, ?string $note = null)
    {
        $this->sender = $sender;
        $this->note = $note;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'sender_id' => $this->sender->id ?? null,
            'sender_name' => $this->sender->name ?? null,
            'avatar' => $this->sender->avatar ?? null,
            'note' => $this->note,
        ];
    }
}
