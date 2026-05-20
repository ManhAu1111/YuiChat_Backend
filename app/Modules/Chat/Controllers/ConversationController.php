<?php

namespace App\Modules\Chat\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $authUserId = auth()->id();

        $conversations = Conversation::whereHas('participants', function ($q) use ($authUserId) {
                $q->where('user_id', $authUserId);
            })
            ->with([
                'lastMessage',
                'participants.user' => function ($q) {
                    $q->select('id', 'name', 'username', 'avatar', 'is_online', 'last_active_at');
                }
            ])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($conversations);
    }

    public function getOrCreate1on1(Request $request)
    {
        $request->validate([
            'target_user_id' => ['required', 'exists:users,id'],
        ]);

        $authUserId = auth()->id();
        $targetUserId = $request->target_user_id;

        // Tìm cuộc hội thoại 1-1 đã tồn tại giữa 2 người này
        $conversation = Conversation::where('is_group', false)
            ->whereHas('participants', function ($q) use ($authUserId) {
                $q->where('user_id', $authUserId);
            })
            ->whereHas('participants', function ($q) use ($targetUserId) {
                $q->where('user_id', $targetUserId);
            })
            ->first();

        if ($conversation) {
            return response()->json($conversation);
        }

        // Nếu chưa có, tạo mới
        return DB::transaction(function () use ($authUserId, $targetUserId) {
            $conversation = Conversation::create([
                'is_group' => false,
            ]);

            // Tạo participant cho cả 2 người
            Participant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $authUserId,
                'role' => 'member',
            ]);

            Participant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $targetUserId,
                'role' => 'member',
            ]);

            return response()->json($conversation);
        });
    }

    public function storeGroup(Request $request)
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $authUserId = auth()->id();
        
        $userIds = array_diff($request->user_ids, [$authUserId]);
        
        if (empty($userIds)) {
            return response()->json(['message' => 'Need at least one other member.'], 422);
        }

        $groupName = $request->name;
        if (empty($groupName)) {
            $users = \App\Models\User::whereIn('id', array_slice($userIds, 0, 2))->pluck('name')->toArray();
            $groupName = auth()->user()->name . ', ' . implode(', ', $users);
            if (count($userIds) > 2) {
                $groupName .= '...';
            }
        }

        return DB::transaction(function () use ($authUserId, $userIds, $groupName) {
            $conversation = Conversation::create([
                'is_group' => true,
                'name' => $groupName,
            ]);

            Participant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $authUserId,
                'role' => 'admin',
            ]);

            foreach ($userIds as $id) {
                Participant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $id,
                    'role' => 'member',
                ]);
            }

            return response()->json($conversation->load(['participants.user' => function ($q) {
                $q->select('id', 'name', 'username', 'avatar', 'is_online', 'last_active_at');
            }]));
        });
    }

    public function addMembers(Request $request, $id)
    {
        $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $authUserId = auth()->id();
        $conversation = Conversation::findOrFail($id);

        if (!$conversation->is_group) {
            return response()->json(['message' => 'Not a group chat.'], 400);
        }

        $adminParticipant = Participant::where('conversation_id', $id)
            ->where('user_id', $authUserId)
            ->where('role', 'admin')
            ->first();

        if (!$adminParticipant) {
            return response()->json(['message' => 'Only admins can add members.'], 403);
        }

        return DB::transaction(function () use ($conversation, $request) {
            $added = 0;
            foreach ($request->user_ids as $userId) {
                $exists = Participant::where('conversation_id', $conversation->id)
                    ->where('user_id', $userId)
                    ->exists();

                if (!$exists) {
                    Participant::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                        'role' => 'member',
                    ]);
                    $added++;
                }
            }
            
            // Reload with participants
            return response()->json($conversation->load(['participants.user' => function ($q) {
                $q->select('id', 'name', 'username', 'avatar', 'is_online', 'last_active_at');
            }]));
        });
    }

    public function removeMember($id, $userId)
    {
        $authUserId = auth()->id();
        $conversation = Conversation::findOrFail($id);

        if (!$conversation->is_group) {
            return response()->json(['message' => 'Not a group chat.'], 400);
        }

        $participantToRemove = Participant::where('conversation_id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$participantToRemove) {
            return response()->json(['message' => 'User not in group.'], 404);
        }

        if ($userId != $authUserId) {
            $adminParticipant = Participant::where('conversation_id', $id)
                ->where('user_id', $authUserId)
                ->where('role', 'admin')
                ->first();

            if (!$adminParticipant) {
                return response()->json(['message' => 'Only admins can remove members.'], 403);
            }
        }

        $participantToRemove->delete();

        if ($userId == $authUserId && $participantToRemove->role === 'admin') {
            $otherAdmin = Participant::where('conversation_id', $id)->where('role', 'admin')->exists();
            if (!$otherAdmin) {
                $oldestMember = Participant::where('conversation_id', $id)->orderBy('id')->first();
                if ($oldestMember) {
                    $oldestMember->update(['role' => 'admin']);
                }
            }
        }

        return response()->json(['message' => 'Member removed successfully.']);
    }

    public function updateGroup(Request $request, $id)
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'] 
        ]);

        $authUserId = auth()->id();
        $conversation = Conversation::findOrFail($id);

        if (!$conversation->is_group) {
            return response()->json(['message' => 'Not a group chat.'], 400);
        }

        $adminParticipant = Participant::where('conversation_id', $id)
            ->where('user_id', $authUserId)
            ->where('role', 'admin')
            ->first();

        if (!$adminParticipant) {
            return response()->json(['message' => 'Only admins can update group info.'], 403);
        }

        $updates = [];
        if ($request->has('name')) {
            $updates['name'] = $request->name;
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $updates['avatar'] = 'storage/' . $path;
        }

        if (!empty($updates)) {
            $conversation->update($updates);
        }

        return response()->json($conversation->load(['participants.user' => function ($q) {
            $q->select('id', 'name', 'username', 'avatar', 'is_online', 'last_active_at');
        }]));
    }
}
