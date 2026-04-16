<?php

namespace App\Modules\User\Services;

use App\Models\Friendship;
use App\Enums\FriendshipStatus;
use App\Models\User;

class FriendshipService
{
    /**
     * Get friendship states for a given user.
     *
     * @param User $user
     * @return array
     */
    public function getFriendshipStates(User $user)
    {
        // Accepted: user is either user_id or friend_id, and status is ACCEPTED
        $acceptedAsUser = Friendship::where('user_id', $user->id)
            ->where('status', FriendshipStatus::ACCEPTED)
            ->pluck('friend_id')
            ->toArray();

        $acceptedAsFriend = Friendship::where('friend_id', $user->id)
            ->where('status', FriendshipStatus::ACCEPTED)
            ->pluck('user_id')
            ->toArray();

        $accepted = array_values(array_unique(array_merge($acceptedAsUser, $acceptedAsFriend)));

        // Pending Sent: user is user_id, status is PENDING
        $pendingSent = Friendship::where('user_id', $user->id)
            ->where('status', FriendshipStatus::PENDING)
            ->pluck('friend_id')
            ->toArray();

        // Pending Received: user is friend_id, status is PENDING
        $pendingReceived = Friendship::where('friend_id', $user->id)
            ->where('status', FriendshipStatus::PENDING)
            ->pluck('user_id')
            ->toArray();

        return [
            'accepted' => $accepted,
            'pending_sent' => $pendingSent,
            'pending_received' => $pendingReceived,
        ];
    }
}
