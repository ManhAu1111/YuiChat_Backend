<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->query('q');

        if (!$query) {
            return response()->json([]);
        }

        $users = User::where(function ($q) use ($query) {
                $q->where('username', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%");
            })
            ->where('id', '!=', auth()->id())
            ->limit(10)
            ->get(['id', 'name', 'username', 'avatar']);

        return response()->json($users);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'avatar' => 'nullable|string',
        ]);

        $user->update($validated);

        return response()->json($user);
    }

    public function show(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();

        // Check if the current user is the target user
        $isSelf = $currentUser->id === $user->id;

        // Check if they are friends
        $isFriend = false;
        if (!$isSelf) {
            $isFriend = $currentUser->friendships()
                ->where('friend_id', $user->id)
                ->where('status', 'accepted')
                ->exists();
        }

        // Only load activeStatus if they are friends or it's the user themselves
        if ($isSelf || $isFriend) {
            $user->load('activeStatus');
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email, // Optional, might want to hide email of non-friends for privacy, but we'll return it for now.
            'avatar' => $user->avatar,
            'is_online' => $user->is_online,
            'status' => $user->activeStatus ? [
                'id' => $user->activeStatus->id,
                'content' => $user->activeStatus->content,
                'icon' => $user->activeStatus->icon,
            ] : null,
        ]);
    }
}
