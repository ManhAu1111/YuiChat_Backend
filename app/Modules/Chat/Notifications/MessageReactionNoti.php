<?php

namespace App\Modules\Chat\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MessageReactionNoti extends Notification
{
    use Queueable;

    protected $message_id;
    protected $reaction;
    protected $sender_name;
    protected $conversation_id;
    protected $message_preview;

    /**
     * Create a new notification instance.
     */
    public function __construct($message_id, $reaction, $sender_name, $conversation_id, $message_preview = null)
    {
        $this->message_id = $message_id;
        $this->reaction = $reaction;
        $this->sender_name = $sender_name;
        $this->conversation_id = $conversation_id;
        $this->message_preview = $message_preview;
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
            'message_id' => $this->message_id,
            'reaction' => $this->reaction,
            'sender_name' => $this->sender_name,
            'conversation_id' => $this->conversation_id,
            'message_preview' => $this->message_preview,
        ];
    }
}
