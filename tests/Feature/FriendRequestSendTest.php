<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendRequestSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_request_creates_pending_row(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        Sanctum::actingAs($alice);

        $response = $this->postJson('/api/friend-requests', ['recipient_id' => $bob->id]);

        $response->assertStatus(201)->assertJsonPath('status', 'pending');
        $this->assertSame(1, Friendship::count());
        $this->assertDatabaseHas('friendships', [
            'sender_id' => $alice->id,
            'recipient_id' => $bob->id,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_request_self(): void
    {
        $alice = User::factory()->create();
        Sanctum::actingAs($alice);

        $this->postJson('/api/friend-requests', ['recipient_id' => $alice->id])
            ->assertStatus(422);
    }

    public function test_duplicate_same_direction_request_returns_422(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'pending']);
        Sanctum::actingAs($alice);

        $this->postJson('/api/friend-requests', ['recipient_id' => $bob->id])
            ->assertStatus(422);
    }

    public function test_request_when_already_friends_returns_422(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'accepted']);
        Sanctum::actingAs($alice);

        $this->postJson('/api/friend-requests', ['recipient_id' => $bob->id])
            ->assertStatus(422);
    }

    public function test_index_lists_incoming_and_outgoing(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'pending']);
        Friendship::create(['sender_id' => $carol->id, 'recipient_id' => $alice->id, 'status' => 'pending']);
        Sanctum::actingAs($alice);

        $response = $this->getJson('/api/friend-requests')->assertOk();

        $response->assertJsonPath('outgoing.0.recipient.id', $bob->id);
        $response->assertJsonPath('incoming.0.sender.id', $carol->id);
    }
}
