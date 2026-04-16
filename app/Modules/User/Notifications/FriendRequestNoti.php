<?php

namespace App\Modules\User\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FriendRequestNoti extends Notification
{
    use Queueable;

    protected $sender_id;
    protected $sender_name;
    protected $avatar;
    protected $note;

    /**
     * Create a new notification instance.
     */
    public function __construct($sender_id, $sender_name, $avatar, $note = null)
    {
        $this->sender_id = $sender_id;
        $this->sender_name = $sender_name;
        $this->avatar = $avatar;
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
            'sender_id' => $this->sender_id,
            'sender_name' => $this->sender_name,
            'avatar' => $this->avatar,
            'note' => $this->note,
        ];
    }
}
