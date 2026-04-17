<?php

namespace App\Modules\User\Services;

use App\Models\Friendship;
use App\Enums\FriendshipStatus;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Notification;
use App\Modules\User\Notifications\FriendRequestNoti;

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

    public function sendRequest(User $sender, int $receiverId)
    {
        if ($sender->id === $receiverId) {
            throw new Exception("Cannot send friend request to yourself.");
        }

        $exists = Friendship::where(function ($query) use ($sender, $receiverId) {
            $query->where('user_id', $sender->id)->where('friend_id', $receiverId);
        })->orWhere(function ($query) use ($sender, $receiverId) {
            $query->where('user_id', $receiverId)->where('friend_id', $sender->id);
        })->exists();

        if ($exists) {
            throw new Exception("Relationship already exists.");
        }

        Friendship::create([
            'user_id' => $sender->id,
            'friend_id' => $receiverId,
            'status' => FriendshipStatus::PENDING
        ]);

        $receiver = User::find($receiverId);
        if ($receiver) {
            Notification::send($receiver, new FriendRequestNoti($sender));
        }
    }

    public function acceptRequest(User $receiver, int $senderId)
    {
        $friendship = Friendship::where('user_id', $senderId)
            ->where('friend_id', $receiver->id)
            ->where('status', FriendshipStatus::PENDING)
            ->first();

        if (!$friendship) {
            throw new Exception("Friend request not found.");
        }

        $friendship->update([
            'status' => FriendshipStatus::ACCEPTED
        ]);
    }

    public function cancelOrDeclineRequest(int $userId1, int $userId2)
    {
        Friendship::where(function ($query) use ($userId1, $userId2) {
            $query->where(function($q) use ($userId1, $userId2) {
                $q->where('user_id', $userId1)->where('friend_id', $userId2);
            })->orWhere(function($q) use ($userId1, $userId2) {
                $q->where('user_id', $userId2)->where('friend_id', $userId1);
            });
        })->where('status', FriendshipStatus::PENDING)->delete();
    }

    public function unfriend(int $userId1, int $userId2)
    {
        Friendship::where(function ($query) use ($userId1, $userId2) {
            $query->where(function($q) use ($userId1, $userId2) {
                $q->where('user_id', $userId1)->where('friend_id', $userId2);
            })->orWhere(function($q) use ($userId1, $userId2) {
                $q->where('user_id', $userId2)->where('friend_id', $userId1);
            });
        })->where('status', FriendshipStatus::ACCEPTED)->delete();
    }
}
