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
    protected $description = 'Check and mark users offline if inactive for 3 minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $offlineUsers = User::where('is_online', true)
            ->where('last_active_at', '<', now()->subMinutes(1))
            ->get();

        foreach ($offlineUsers as $user) {
            $user->is_online = false;
            $user->save();
            
            broadcast(new UserOnlineStatusChanged($user->id, false, $user->last_active_at));
        }

        $this->info("Marked {$offlineUsers->count()} users as offline.");
    }
}
