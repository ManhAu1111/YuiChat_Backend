<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_current_user_profile()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/user');

        $response->assertStatus(200)
                 ->assertJson([
                     'id' => $user->id,
                     'email' => $user->email,
                     'username' => $user->username,
                     'name' => $user->name,
                 ]);
    }

    public function test_guest_cannot_get_profile()
    {
        $response = $this->getJson('/api/user');
        $response->assertStatus(401);
    }

    public function test_can_search_users_by_name_or_username()
    {
        $currentUser = User::factory()->create();

        // Target users
        User::factory()->create(['name' => 'Alice Smith', 'username' => 'alices']);
        User::factory()->create(['name' => 'Bob Jones', 'username' => 'bobj']);
        User::factory()->create(['name' => 'Charlie Smith', 'username' => 'charlies']);

        $response = $this->actingAs($currentUser)->getJson('/api/search?q=Smith');

        $response->assertStatus(200)
                 ->assertJsonCount(2)
                 ->assertJsonFragment(['name' => 'Alice Smith'])
                 ->assertJsonFragment(['name' => 'Charlie Smith'])
                 ->assertJsonMissing(['name' => 'Bob Jones']);
    }

    public function test_search_excludes_current_user()
    {
        $currentUser = User::factory()->create(['name' => 'Alice Current']);

        // Another Alice
        User::factory()->create(['name' => 'Alice Target']);

        $response = $this->actingAs($currentUser)->getJson('/api/search?q=Alice');

        $response->assertStatus(200)
                 ->assertJsonCount(1)
                 ->assertJsonFragment(['name' => 'Alice Target'])
                 ->assertJsonMissing(['name' => 'Alice Current']);
    }

    public function test_search_returns_empty_when_no_query()
    {
        $currentUser = User::factory()->create();
        User::factory()->create(['name' => 'Alice']);

        $response = $this->actingAs($currentUser)->getJson('/api/search');

        $response->assertStatus(200)
                 ->assertExactJson([]);
    }
}
