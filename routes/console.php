<?php
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Modules\Chat\Notifications\GroupAddedNoti;
use App\Models\Conversation;

Artisan::command('test:broadcast', function () {
    $user = User::first();
    $conversation = Conversation::first();
    if ($user && $conversation) {
        $user->notify(new GroupAddedNoti($user, $conversation));
        $this->info("Notification sent to user {$user->id}");
    } else {
        $this->error("No user or conversation found");
    }
});
