<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Conversation;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_conversations()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::create(['is_group' => false]);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user1->id, 'role' => 'member']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user2->id, 'role' => 'member']);

        $response = $this->actingAs($user1)->getJson('/api/conversations');

        $response->assertStatus(200)
                 ->assertJsonCount(1)
                 ->assertJsonFragment(['id' => $conversation->id]);
    }

    public function test_user_can_create_1on1_conversation()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $response = $this->actingAs($user1)->postJson('/api/1on1', [
            'target_user_id' => $user2->id
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['id', 'is_group']);

        $this->assertDatabaseHas('conversations', [
            'is_group' => false
        ]);

        $this->assertDatabaseHas('participants', [
            'user_id' => $user1->id,
            'role' => 'member'
        ]);

        $this->assertDatabaseHas('participants', [
            'user_id' => $user2->id,
            'role' => 'member'
        ]);
    }

    public function test_user_can_create_group_conversation()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $response = $this->actingAs($user1)->postJson('/api/groups', [
            'name' => 'Test Group',
            'user_ids' => [$user1->id, $user2->id, $user3->id]
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'Test Group', 'is_group' => true]);

        $conversationId = $response->json('id');

        $this->assertDatabaseHas('participants', [
            'conversation_id' => $conversationId,
            'user_id' => $user1->id,
            'role' => 'admin'
        ]);

        $this->assertDatabaseHas('participants', [
            'conversation_id' => $conversationId,
            'user_id' => $user2->id,
            'role' => 'member'
        ]);
    }

    public function test_admin_can_add_members_to_group()
    {
        $admin = User::factory()->create();
        $user1 = User::factory()->create();
        $newUser = User::factory()->create();

        $conversation = Conversation::create(['is_group' => true, 'name' => 'Group']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $admin->id, 'role' => 'admin']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user1->id, 'role' => 'member']);

        $response = $this->actingAs($admin)->postJson("/api/groups/{$conversation->id}/members", [
            'user_ids' => [$newUser->id]
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $newUser->id,
            'role' => 'member'
        ]);
    }

    public function test_non_admin_cannot_add_members()
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $newUser = User::factory()->create();

        $conversation = Conversation::create(['is_group' => true, 'name' => 'Group']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $admin->id, 'role' => 'admin']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $member->id, 'role' => 'member']);

        $response = $this->actingAs($member)->postJson("/api/groups/{$conversation->id}/members", [
            'user_ids' => [$newUser->id]
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_remove_member()
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $conversation = Conversation::create(['is_group' => true, 'name' => 'Group']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $admin->id, 'role' => 'admin']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $member->id, 'role' => 'member']);

        $response = $this->actingAs($admin)->deleteJson("/api/groups/{$conversation->id}/members/{$member->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $member->id
        ]);
    }

    public function test_member_can_leave_group()
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $conversation = Conversation::create(['is_group' => true, 'name' => 'Group']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $admin->id, 'role' => 'admin']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $member->id, 'role' => 'member']);

        // Hành động rời nhóm: người dùng tự xoá chính mình
        $response = $this->actingAs($member)->deleteJson("/api/groups/{$conversation->id}/members/{$member->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $member->id
        ]);
    }
}
