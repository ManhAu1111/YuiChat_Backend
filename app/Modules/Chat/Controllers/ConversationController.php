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
                    $q->select('id', 'name', 'username', 'avatar', 'is_online');
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
}
