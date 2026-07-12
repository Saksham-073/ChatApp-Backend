<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendRequestCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_sender_can_cancel_own_pending_request(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $friendship = Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'pending']);
        Sanctum::actingAs($alice);

        $this->deleteJson("/api/friend-requests/{$friendship->id}")->assertStatus(204);

        $this->assertSame(0, Friendship::count());
    }

    public function test_recipient_can_decline_pending_request(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $friendship = Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'pending']);
        Sanctum::actingAs($bob);

        $this->deleteJson("/api/friend-requests/{$friendship->id}")->assertStatus(204);

        $this->assertSame(0, Friendship::count());
    }

    public function test_non_participant_cannot_cancel(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $eve = User::factory()->create();
        $friendship = Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'pending']);
        Sanctum::actingAs($eve);

        $this->deleteJson("/api/friend-requests/{$friendship->id}")->assertStatus(403);
        $this->assertSame(1, Friendship::count());
    }

    public function test_cannot_cancel_already_accepted_friendship(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $friendship = Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'accepted']);
        Sanctum::actingAs($alice);

        $this->deleteJson("/api/friend-requests/{$friendship->id}")->assertStatus(409);
        $this->assertSame(1, Friendship::count());
    }

    public function test_cancelling_missing_request_returns_404(): void
    {
        $alice = User::factory()->create();
        Sanctum::actingAs($alice);

        $this->deleteJson('/api/friend-requests/999999')->assertStatus(404);
    }

    public function test_after_decline_users_can_request_again(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $friendship = Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'pending']);
        Sanctum::actingAs($bob);
        $this->deleteJson("/api/friend-requests/{$friendship->id}")->assertStatus(204);

        Sanctum::actingAs($alice);
        $this->postJson('/api/friend-requests', ['recipient_id' => $bob->id])->assertStatus(201);
    }
}
