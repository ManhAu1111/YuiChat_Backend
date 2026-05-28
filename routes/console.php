<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\User;
use App\Modules\Chat\Notifications\GroupAddedNoti;
use App\Models\Conversation;

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
| Tự động kiểm tra và đánh dấu user offline nếu không gửi heartbeat
| trong vòng 90 giây. Frontend gửi heartbeat mỗi 30 giây nên ngưỡng
| 90 giây cho phép tối đa 2 lần heartbeat bị miss trước khi offline.
|
| Chạy bằng lệnh: php artisan schedule:work
*/
Schedule::command('app:check-offline-users')->everyMinute();

/*
|--------------------------------------------------------------------------
| Dev / Test Commands
|--------------------------------------------------------------------------
*/
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
