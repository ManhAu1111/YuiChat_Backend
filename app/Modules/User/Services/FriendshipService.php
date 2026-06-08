<?php

namespace App\Modules\User\Services;

use App\Models\Friendship;
use App\Enums\FriendshipStatus;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Participant;
use Exception;
use Illuminate\Support\Facades\Notification;
use App\Modules\User\Notifications\FriendRequestNoti;
use App\Modules\User\Notifications\FriendAcceptedNoti;
use App\Modules\User\Notifications\FriendDeclinedNoti;

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

        // Automatically create a 1on1 conversation if it doesn't exist
        $conversation = Conversation::where('is_group', false)
            ->whereHas('participants', function ($q) use ($receiver) {
                $q->where('user_id', $receiver->id);
            })
            ->whereHas('participants', function ($q) use ($senderId) {
                $q->where('user_id', $senderId);
            })
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'is_group' => false,
            ]);

            Participant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $receiver->id,
                'role' => 'member',
            ]);

            Participant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $senderId,
                'role' => 'member',
            ]);
        } else {
            // Touch to update updated_at so it jumps to the top of the chat list
            $conversation->touch();
        }

        // Delete the database notification for this friend request on the receiver's end
        $receiver->notifications()
            ->where('type', 'App\\Modules\\User\\Notifications\\FriendRequestNoti')
            ->where('data->sender_id', $senderId)
            ->delete();

        // Notify the original sender in real-time so their FriendshipButton
        // immediately transitions from "Đã gửi lời mời" → "Bạn bè".
        $originalSender = User::find($senderId);
        if ($originalSender) {
            Notification::send($originalSender, new FriendAcceptedNoti($receiver));
        }
    }

    public function cancelOrDeclineRequest(int $userId1, int $userId2)
    {
        // Fetch the record BEFORE deleting so we know which direction the
        // request was sent — this tells us who the "other party" is to notify.
        $friendship = Friendship::where(function ($query) use ($userId1, $userId2) {
            $query->where(function($q) use ($userId1, $userId2) {
                $q->where('user_id', $userId1)->where('friend_id', $userId2);
            })->orWhere(function($q) use ($userId1, $userId2) {
                $q->where('user_id', $userId2)->where('friend_id', $userId1);
            });
        })->where('status', FriendshipStatus::PENDING)->first();

        if (!$friendship) {
            return; // Already gone — nothing to do.
        }

        // Delete the database notification for this friend request on the receiver's end
        $receiver = User::find($friendship->friend_id);
        if ($receiver) {
            $receiver->notifications()
                ->where('type', 'App\\Modules\\User\\Notifications\\FriendRequestNoti')
                ->where('data->sender_id', $friendship->user_id)
                ->delete();
        }

        $friendship->delete();

        // Determine who performed the action (userId1) and who needs notifying.
        // The "other party" is whoever is NOT userId1 in the friendship row.
        $actionUserId  = $userId1;
        $targetUserId  = ($friendship->user_id === $userId1)
            ? $friendship->friend_id
            : $friendship->user_id;

        $actionUser = User::find($actionUserId);
        $targetUser = User::find($targetUserId);

        if ($actionUser && $targetUser) {
            // Broadcast a silent real-time blip so the other party's
            // FriendshipButton snaps back to "Thêm bạn" instantly.
            Notification::send($targetUser, new FriendDeclinedNoti($actionUser));
        }
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
