<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Events\UserOnlineStatusChanged;

class CheckOfflineUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-offline-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark users offline if they have not sent a heartbeat in the last 90 seconds';

    /**
     * Execute the console command.
     *
     * Logic:
     * - Frontend gửi heartbeat mỗi 30 giây.
     * - Ngưỡng 90 giây = cho phép bỏ sót tối đa 2 heartbeat liên tiếp
     *   (ví dụ: mạng chập chờn tạm thời) trước khi bị đánh dấu offline.
     * - Command này được scheduler gọi mỗi phút qua `php artisan schedule:work`.
     */
    public function handle(): void
    {
        $threshold = now()->subSeconds(90);

        $offlineUsers = User::where('is_online', true)
            ->where('last_active_at', '<', $threshold)
            ->get();

        foreach ($offlineUsers as $user) {
            $user->is_online = false;
            $user->save();

            broadcast(new UserOnlineStatusChanged($user->id, false, $user->last_active_at));
        }

        $count = $offlineUsers->count();
        $this->info("Marked {$count} user(s) as offline (threshold: 90s).");
    }
}
