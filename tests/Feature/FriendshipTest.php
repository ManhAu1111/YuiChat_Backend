<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Friendship;
use App\Enums\FriendshipStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use App\Modules\User\Notifications\FriendRequestNoti;
use App\Modules\User\Notifications\FriendAcceptedNoti;
use App\Modules\User\Notifications\FriendDeclinedNoti;

class FriendshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_send_friend_request()
    {
        Notification::fake();

        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $response = $this->actingAs($sender)->postJson('/api/friendships/request', [
            'friend_id' => $receiver->id,
            'note' => 'Let be friends!'
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success'
                 ]);

        // Check if database was updated correctly
        $this->assertDatabaseHas('friendships', [
            'user_id' => $sender->id,
            'friend_id' => $receiver->id,
            'status' => FriendshipStatus::PENDING
        ]);

        // Check if notification was sent
        Notification::assertSentTo(
            $receiver,
            FriendRequestNoti::class,
            function ($notification) use ($sender) {
                return $notification->sender->id === $sender->id;
            }
        );
    }

    public function test_user_can_accept_friend_request()
    {
        Notification::fake();

        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        // Create pending request
        Friendship::create([
            'user_id' => $sender->id,
            'friend_id' => $receiver->id,
            'status' => FriendshipStatus::PENDING
        ]);

        $response = $this->actingAs($receiver)->postJson('/api/friendships/accept', [
            'friend_id' => $sender->id
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success'
                 ]);

        // Check database
        $this->assertDatabaseHas('friendships', [
            'user_id' => $sender->id,
            'friend_id' => $receiver->id,
            'status' => FriendshipStatus::ACCEPTED
        ]);

        // Check if acceptance notification was sent to sender
        Notification::assertSentTo(
            $sender,
            FriendAcceptedNoti::class,
            function ($notification) use ($receiver) {
                return $notification->acceptor->id === $receiver->id;
            }
        );
    }

    public function test_user_can_decline_friend_request()
    {
        Notification::fake();

        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        // Create pending request
        Friendship::create([
            'user_id' => $sender->id,
            'friend_id' => $receiver->id,
            'status' => FriendshipStatus::PENDING
        ]);

        $response = $this->actingAs($receiver)->deleteJson('/api/friendships/decline', [
            'friend_id' => $sender->id
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success'
                 ]);

        // Check database (friendship should be deleted)
        $this->assertDatabaseMissing('friendships', [
            'user_id' => $sender->id,
            'friend_id' => $receiver->id
        ]);

        // Check if decline notification was sent to sender
        Notification::assertSentTo(
            $sender,
            FriendDeclinedNoti::class,
            function ($notification) use ($receiver) {
                return $notification->decliner->id === $receiver->id;
            }
        );
    }
}
