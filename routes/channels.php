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
