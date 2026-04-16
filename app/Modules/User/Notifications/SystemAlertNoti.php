<?php

namespace App\Modules\User\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SystemAlertNoti extends Notification
{
    use Queueable;

    protected $title;
    protected $content;
    protected $link;
    protected $icon_type;

    /**
     * Create a new notification instance.
     */
    public function __construct($title, $content, $link = null, $icon_type = null)
    {
        $this->title = $title;
        $this->content = $content;
        $this->link = $link;
        $this->icon_type = $icon_type;
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
            'title' => $this->title,
            'content' => $this->content,
            'link' => $this->link,
            'icon_type' => $this->icon_type,
        ];
    }
}
