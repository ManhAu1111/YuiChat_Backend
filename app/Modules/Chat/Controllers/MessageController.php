<?php

namespace App\Modules\Chat\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Events\MessageSent;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function index(Request $request, $conversationId)
    {
        $authUserId = auth()->id();

        // Kiểm tra xem user có phải là thành viên của cuộc hội thoại không
        $isParticipant = Participant::where('conversation_id', $conversationId)
            ->where('user_id', $authUserId)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Unauthorized access to this conversation.'], 403);
        }

        $messages = Message::where('conversation_id', $conversationId)
            ->with(['sender:id,name,username,avatar'])
            ->orderBy('created_at', 'asc')
            ->paginate(50); // Mặc định cũ nhất đến mới nhất, có phân trang

        return response()->json($messages);
    }

    public function store(Request $request, $conversationId)
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'type' => ['nullable', 'string', 'in:text,image,file'],
        ]);

        $authUserId = auth()->id();

        // Kiểm tra quyền
        $isParticipant = Participant::where('conversation_id', $conversationId)
            ->where('user_id', $authUserId)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Action unauthorized.'], 403);
        }

        return DB::transaction(function () use ($conversationId, $authUserId, $validated) {
            // Tạo bản ghi tin nhắn mới
            $message = Message::create([
                'conversation_id' => $conversationId,
                'sender_id' => $authUserId,
                'content' => $validated['content'],
                'type' => $validated['type'] ?? 'text',
            ]);

            // Cập nhật last_message_id của cuộc hội thoại
            Conversation::where('id', $conversationId)->update([
                'last_message_id' => $message->id,
                'updated_at' => now(), // Cập nhật updated_at để sắp xếp cuộc hội thoại ở index()
            ]);

            broadcast(new MessageSent($message->load('sender:id,name,username,avatar')))->toOthers();

            // Trả về tin nhắn vừa tạo kèm thông tin người gửi
            return response()->json($message->load('sender:id,name,username,avatar'), 201);
        });
    }
}
