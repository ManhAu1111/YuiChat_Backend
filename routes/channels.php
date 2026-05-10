<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Participant;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    // Kiểm tra xem user có phải là thành viên của phòng chat này không
    return Participant::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();
});

// Authorize the private notification channel Laravel uses for all
// ShouldBroadcast notifications (BroadcastNotificationCreated event).
// Format: App.Models.User.{userId}
// A user can only subscribe to their own channel.
Broadcast::channel('App.Models.User.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

