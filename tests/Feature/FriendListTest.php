<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendListTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_accepted_friends(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'accepted']);
        Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $carol->id, 'status' => 'pending']);
        Sanctum::actingAs($alice);

        $response = $this->getJson('/api/friends')->assertOk();

        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $bob->id);
    }

    public function test_unfriend_deletes_the_row_regardless_of_direction(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        Friendship::create(['sender_id' => $bob->id, 'recipient_id' => $alice->id, 'status' => 'accepted']);
        Sanctum::actingAs($alice);

        $this->deleteJson("/api/friends/{$bob->id}")->assertStatus(204);

        $this->assertSame(0, Friendship::count());
    }

    public function test_unfriend_non_friend_returns_404(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        Sanctum::actingAs($alice);

        $this->deleteJson("/api/friends/{$bob->id}")->assertStatus(404);
    }
}
