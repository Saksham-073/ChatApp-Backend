<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendRequestAcceptTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_can_accept_pending_request(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $friendship = Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'pending']);
        Sanctum::actingAs($bob);

        $this->postJson("/api/friend-requests/{$friendship->id}/accept")
            ->assertOk()
            ->assertJsonPath('status', 'accepted');

        $this->assertDatabaseHas('friendships', ['id' => $friendship->id, 'status' => 'accepted']);
    }

    public function test_sender_cannot_accept_own_request(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $friendship = Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'pending']);
        Sanctum::actingAs($alice);

        $this->postJson("/api/friend-requests/{$friendship->id}/accept")
            ->assertStatus(403);
    }

    public function test_stranger_cannot_accept_others_request(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $eve = User::factory()->create();
        $friendship = Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'pending']);
        Sanctum::actingAs($eve);

        $this->postJson("/api/friend-requests/{$friendship->id}/accept")
            ->assertStatus(403);
    }

    public function test_crossed_request_auto_accepts_instead_of_creating_second_row(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'pending']);
        Sanctum::actingAs($bob);

        $response = $this->postJson('/api/friend-requests', ['recipient_id' => $alice->id]);

        $response->assertOk()->assertJsonPath('status', 'accepted');
        $this->assertSame(1, Friendship::count());
        $this->assertDatabaseHas('friendships', [
            'sender_id' => $alice->id,
            'recipient_id' => $bob->id,
            'status' => 'accepted',
        ]);
    }
}
