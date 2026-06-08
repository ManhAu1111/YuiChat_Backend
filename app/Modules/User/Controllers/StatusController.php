<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class StatusController extends Controller
{
    /**
     * Get active statuses for the authenticated user and their friends.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Get friends
        $friends = $user->friendships()
            ->where('status', 'accepted')
            ->get()
            ->map(function ($friendship) use ($user) {
                return $friendship->user_id === $user->id 
                    ? $friendship->friend 
                    : $friendship->user;
            });

        // Add the current user to the list
        $friends->push($user);

        // Load active statuses
        $friends->load('activeStatus');

        // Transform the data
        $data = $friends->map(function ($friend) {
            return [
                'id' => $friend->id,
                'name' => $friend->name,
                'avatar' => $friend->avatar,
                'is_online' => $friend->is_online,
                'status' => $friend->activeStatus ? [
                    'id' => $friend->activeStatus->id,
                    'content' => $friend->activeStatus->content,
                    'icon' => $friend->activeStatus->icon,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Update or create a status for the authenticated user.
     */
    public function store(Request $request)
    {
        $request->validate([
            'icon' => 'nullable|string|max:50',
            'content' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();

        // Update or create the status for this user
        $status = \App\Models\UserStatus::updateOrCreate(
            ['user_id' => $user->id],
            [
                'icon' => $request->icon,
                'content' => $request->content,
                'expires_at' => now()->addHours(24),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $status->id,
                'content' => $status->content,
                'icon' => $status->icon,
            ],
            'message' => 'Cập nhật trạng thái thành công'
        ]);
    }
}
