<?php

namespace App\Modules\Chat\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Events\MessageSent;
use App\Models\Participant;
use Illuminate\Http\Request;

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

        $messages = Message::where('conversation_id', (int) $conversationId)
            ->with(['sender:id,name,username,avatar'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        // Reverse to ascending for the frontend
        $messages->setCollection($messages->getCollection()->reverse()->values());

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
            'conversation_id' => (int) $conversationId,
            'sender_id'       => (int) $authUserId,
            'content'         => $validated['content'] ?? null,
            'type'            => $type,
            'metadata'        => $metadata,
            'attachments'     => $attachmentsData, // Embedded attachments
            'reactions'       => [], // Empty array for future reactions
        ]);

        // Cập nhật last_message_id của cuộc hội thoại (MariaDB)
        Conversation::where('id', $conversationId)->update([
            'last_message_id' => $message->_id ?? $message->id,
            'updated_at'      => now(),
        ]);

        $messageWithRelations = $message->load(['sender:id,name,username,avatar']);

        broadcast(new MessageSent($messageWithRelations))->toOthers();

        return response()->json($messageWithRelations, 201);
    }

    public function forwardMessages(Request $request)
    {
        $validated = $request->validate([
            'target_conversation_ids'   => ['required', 'array', 'min:1'],
            'target_conversation_ids.*' => ['integer'],
            'message_ids'               => ['nullable', 'array'],
            'message_ids.*'             => ['string'],
            'attachments'               => ['nullable', 'array'],
            'attachments.*.file_url'    => ['required', 'string'],
            'attachments.*.file_name'   => ['nullable', 'string', 'max:255'],
            'attachments.*.file_type'   => ['nullable', 'string', 'max:100'],
            'attachments.*.file_size'   => ['nullable', 'integer', 'min:0'],
            'additional_text'           => ['nullable', 'string', 'max:2000'],
        ]);

        $authUserId = auth()->id();
        $targetIds = $validated['target_conversation_ids'];

        // Verify the user is a participant in all target conversations
        $validConversationsCount = Participant::whereIn('conversation_id', $targetIds)
            ->where('user_id', $authUserId)
            ->count();

        if ($validConversationsCount !== count($targetIds)) {
            return response()->json(['message' => 'Unauthorized access to some conversations.'], 403);
        }

        // Fetch original messages if message_ids are provided
        $originalMessages = collect();
        if (!empty($validated['message_ids'])) {
            $originalMessages = Message::whereIn('_id', $validated['message_ids'])->get();
        }

        $newMessagesCount = 0;
        $createdMessages = [];
        
        foreach ($targetIds as $conversationId) {
            $lastMsg = null;

            // 1. Forward original messages
            foreach ($originalMessages as $origMsg) {
                $metadata = $origMsg->metadata ?? [];
                $metadata['is_forwarded'] = true;

                $lastMsg = Message::create([
                    'conversation_id' => (int) $conversationId,
                    'sender_id'       => (int) $authUserId,
                    'content'         => $origMsg->content,
                    'type'            => $origMsg->type,
                    'metadata'        => $metadata,
                    'attachments'     => $origMsg->attachments,
                    'reactions'       => [],
                ]);
                $newMessagesCount++;
                
                $msgWithRelations = $lastMsg->load(['sender:id,name,username,avatar']);
                $createdMessages[] = $msgWithRelations;
                broadcast(new MessageSent($msgWithRelations))->toOthers();
            }

            // 2. Forward specific attachments (if provided instead of/along with whole messages)
            if (!empty($validated['attachments'])) {
                $attachmentsData = $validated['attachments'];
                $type = str_starts_with($attachmentsData[0]['file_type'] ?? '', 'image/') ? 'image' : 'file';
                
                $metadata = null;
                if (count($attachmentsData) === 1) {
                    $metadata = [
                        'file_name' => $attachmentsData[0]['file_name'] ?? null,
                        'file_size' => $attachmentsData[0]['file_size'] ?? null,
                        'file_type' => $attachmentsData[0]['file_type'] ?? null,
                    ];
                } else {
                    $metadata = ['file_count' => count($attachmentsData)];
                }
                $metadata['is_forwarded'] = true;

                $lastMsg = Message::create([
                    'conversation_id' => (int) $conversationId,
                    'sender_id'       => (int) $authUserId,
                    'content'         => null,
                    'type'            => $type,
                    'metadata'        => $metadata,
                    'attachments'     => $attachmentsData,
                    'reactions'       => [],
                ]);
                $newMessagesCount++;
                
                $msgWithRelations = $lastMsg->load(['sender:id,name,username,avatar']);
                $createdMessages[] = $msgWithRelations;
                broadcast(new MessageSent($msgWithRelations))->toOthers();
            }

            // 3. Send additional text if provided
            if (!empty($validated['additional_text'])) {
                $lastMsg = Message::create([
                    'conversation_id' => (int) $conversationId,
                    'sender_id'       => (int) $authUserId,
                    'content'         => $validated['additional_text'],
                    'type'            => 'text',
                    'metadata'        => null,
                    'attachments'     => [],
                    'reactions'       => [],
                ]);
                $newMessagesCount++;
                
                $msgWithRelations = $lastMsg->load(['sender:id,name,username,avatar']);
                $createdMessages[] = $msgWithRelations;
                broadcast(new MessageSent($msgWithRelations))->toOthers();
            }

            // Update conversation last_message_id
            if ($lastMsg) {
                Conversation::where('id', $conversationId)->update([
                    'last_message_id' => $lastMsg->_id ?? $lastMsg->id,
                    'updated_at'      => now(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Successfully forwarded messages.',
            'count' => $newMessagesCount,
            'messages' => $createdMessages
        ], 200);
    }
}
