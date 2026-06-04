<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Participant;
use App\Events\MessageSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Truncate MongoDB collections used in tests since RefreshDatabase only resets SQL
        Message::truncate();
    }

    public function test_user_can_fetch_messages()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['is_group' => false]);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user1->id, 'role' => 'member']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user2->id, 'role' => 'member']);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user2->id,
            'content' => 'Hello there',
            'type' => 'text',
            'attachments' => []
        ]);

        $response = $this->actingAs($user1)->getJson("/api/conversations/{$conversation->id}/messages");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'content', 'sender', 'attachments']
                     ]
                 ])
                 ->assertJsonFragment(['content' => 'Hello there']);
    }

    public function test_user_cannot_fetch_messages_of_unauthorized_conversation()
    {
        $user = User::factory()->create();
        
        $conversation = Conversation::create(['is_group' => false]);
        // Participant is NOT $user

        $response = $this->actingAs($user)->getJson("/api/conversations/{$conversation->id}/messages");

        $response->assertStatus(403);
    }

    public function test_user_can_send_text_message()
    {
        Event::fake([MessageSent::class]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['is_group' => false]);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user1->id, 'role' => 'member']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user2->id, 'role' => 'member']);

        $response = $this->actingAs($user1)->postJson("/api/conversations/{$conversation->id}/messages", [
            'content' => 'This is a test message'
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['content' => 'This is a test message']);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $user1->id,
            'content' => 'This is a test message'
        ], 'mongodb');

        Event::assertDispatched(MessageSent::class);
    }

    public function test_user_can_send_message_with_attachments()
    {
        Event::fake([MessageSent::class]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['is_group' => false]);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user1->id, 'role' => 'member']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user2->id, 'role' => 'member']);

        $response = $this->actingAs($user1)->postJson("/api/conversations/{$conversation->id}/messages", [
            'content' => 'Look at this photo',
            'attachments' => [
                [
                    'file_url' => 'http://example.com/photo.jpg',
                    'file_name' => 'photo.jpg',
                    'file_type' => 'image/jpeg',
                    'file_size' => 1024
                ]
            ]
        ]);

        $response->assertStatus(201);

        $messageId = $response->json('message._id') ?? $response->json('_id') ?? $response->json('id');
        
        // Assert in MongoDB
        $message = Message::find($messageId);
        $this->assertNotNull($message, "Message not found in MongoDB");
        $this->assertIsArray($message->attachments);
        $this->assertCount(1, $message->attachments);
        $this->assertEquals('http://example.com/photo.jpg', $message->attachments[0]['file_url']);
    }

    public function test_user_cannot_send_empty_message()
    {
        $user1 = User::factory()->create();
        
        $conversation = Conversation::create(['is_group' => false]);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user1->id, 'role' => 'member']);

        $response = $this->actingAs($user1)->postJson("/api/conversations/{$conversation->id}/messages", [
            'content' => ''
        ]);

        $response->assertStatus(422);
    }
}
