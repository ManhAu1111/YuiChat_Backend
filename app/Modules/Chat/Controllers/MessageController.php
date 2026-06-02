<?php

namespace App\Modules\Chat\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
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

        $isParticipant = Participant::where('conversation_id', $conversationId)
            ->where('user_id', $authUserId)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Unauthorized access to this conversation.'], 403);
        }

        $messages = Message::where('conversation_id', $conversationId)
            ->with(['sender:id,name,username,avatar', 'attachments'])
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        return response()->json($messages);
    }

    public function store(Request $request, $conversationId)
    {
        $validated = $request->validate([
            'content'        => ['nullable', 'string'],
            'type'           => ['nullable', 'string', 'in:text,image,file'],
            'attachment_url' => ['nullable', 'string'],
            'file_name'      => ['nullable', 'string', 'max:255'],
            'file_type'      => ['nullable', 'string', 'max:100'],
            'file_size'      => ['nullable', 'integer', 'min:0'],
            'attachments'    => ['nullable', 'array'],
            'attachments.*.file_url'  => ['required', 'string'],
            'attachments.*.file_name' => ['nullable', 'string', 'max:255'],
            'attachments.*.file_type' => ['nullable', 'string', 'max:100'],
            'attachments.*.file_size' => ['nullable', 'integer', 'min:0'],
        ]);

        $hasAttachments = !empty($validated['attachments']) || !empty($validated['attachment_url']);

        // Cần có ít nhất content hoặc attachment
        if (empty($validated['content']) && !$hasAttachments) {
            return response()->json(['message' => 'Tin nhắn phải có nội dung hoặc file đính kèm.'], 422);
        }

        $authUserId = auth()->id();

        $isParticipant = Participant::where('conversation_id', $conversationId)
            ->where('user_id', $authUserId)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Action unauthorized.'], 403);
        }

        return DB::transaction(function () use ($conversationId, $authUserId, $validated) {
            $attachmentsData = $validated['attachments'] ?? [];
            if (empty($attachmentsData) && !empty($validated['attachment_url'])) {
                $attachmentsData[] = [
                    'file_url'  => $validated['attachment_url'],
                    'file_name' => $validated['file_name'] ?? null,
                    'file_type' => $validated['file_type'] ?? null,
                    'file_size' => $validated['file_size'] ?? null,
                ];
            }

            // Xác định type: nếu không truyền lên thì suy ra từ attachment
            $type = $validated['type'] ?? 'text';
            if ($type === 'text' && !empty($attachmentsData)) {
                $type = str_starts_with($attachmentsData[0]['file_type'] ?? '', 'image/') ? 'image' : 'file';
            }

            // Lưu metadata file vào cột metadata của message
            $metadata = null;
            if (!empty($attachmentsData)) {
                if (count($attachmentsData) === 1) {
                    $metadata = [
                        'file_name' => $attachmentsData[0]['file_name'] ?? null,
                        'file_size' => $attachmentsData[0]['file_size'] ?? null,
                        'file_type' => $attachmentsData[0]['file_type'] ?? null,
                    ];
                } else {
                    $metadata = ['file_count' => count($attachmentsData)];
                }
            }

            $message = Message::create([
                'conversation_id' => $conversationId,
                'sender_id'       => $authUserId,
                'content'         => $validated['content'] ?? null,
                'type'            => $type,
                'metadata'        => $metadata,
            ]);

            // Tạo record Attachment nếu có file đính kèm
            foreach ($attachmentsData as $att) {
                Attachment::create([
                    'message_id'  => $message->id,
                    'file_url'    => $att['file_url'],
                    'file_type'   => $att['file_type'] ?? 'application/octet-stream',
                    'file_name'   => $att['file_name'] ?? null,
                    'file_size'   => $att['file_size'] ?? null,
                    'source_type' => 0,
                ]);
            }

            // Cập nhật last_message_id của cuộc hội thoại
            Conversation::where('id', $conversationId)->update([
                'last_message_id' => $message->id,
                'updated_at'      => now(),
            ]);

            $messageWithRelations = $message->load(['sender:id,name,username,avatar', 'attachments']);

            broadcast(new MessageSent($messageWithRelations))->toOthers();

            return response()->json($messageWithRelations, 201);
        });
    }
}
