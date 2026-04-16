<?php

namespace App\Modules\Chat\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GroupInviteNoti extends Notification
{
    use Queueable;

    protected $group_id;
    protected $group_name;
    protected $inviter_name;
    protected $group_avatar;

    /**
     * Create a new notification instance.
     */
    public function __construct($group_id, $group_name, $inviter_name, $group_avatar = null)
    {
        $this->group_id = $group_id;
        $this->group_name = $group_name;
        $this->inviter_name = $inviter_name;
        $this->group_avatar = $group_avatar;
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
            'group_id' => $this->group_id,
            'group_name' => $this->group_name,
            'inviter_name' => $this->inviter_name,
            'group_avatar' => $this->group_avatar,
        ];
    }
}
