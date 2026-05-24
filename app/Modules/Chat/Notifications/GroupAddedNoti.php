<?php

namespace App\Modules\Chat\Notifications;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;

class GroupAddedNoti extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public User $adder;
    public Conversation $conversation;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $adder, Conversation $conversation)
    {
        $this->adder = $adder;
        $this->conversation = $conversation;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'adder_id' => $this->adder->id,
            'adder_name' => $this->adder->name,
            'adder_avatar' => $this->adder->avatar ?? null,
            'conversation_id' => $this->conversation->id,
            'conversation_name' => $this->conversation->name,
            'message' => "Bạn đã được thêm vào nhóm " . $this->conversation->name,
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage([
            'adder_id' => $this->adder->id,
            'adder_name' => $this->adder->name,
            'adder_avatar' => $this->adder->avatar ?? null,
            'conversation_id' => $this->conversation->id,
            'conversation_name' => $this->conversation->name,
            'message' => "Bạn đã được thêm vào nhóm " . $this->conversation->name,
        ]))->onConnection('sync');
    }
}
