<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendshipStatusExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_index_reports_each_relationship_state(): void
    {
        $alice = User::factory()->create();
        $stranger = User::factory()->create();
        $sentTo = User::factory()->create();
        $receivedFrom = User::factory()->create();
        $friend = User::factory()->create();

        Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $sentTo->id, 'status' => 'pending']);
        $received = Friendship::create(['sender_id' => $receivedFrom->id, 'recipient_id' => $alice->id, 'status' => 'pending']);
        Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $friend->id, 'status' => 'accepted']);

        Sanctum::actingAs($alice);
        $response = $this->getJson('/api/users')->assertOk();
        $byId = collect($response->json())->keyBy('id');

        $this->assertSame('none', $byId[$stranger->id]['friendship_status']);
        $this->assertSame('pending_sent', $byId[$sentTo->id]['friendship_status']);
        $this->assertSame('pending_received', $byId[$receivedFrom->id]['friendship_status']);
        $this->assertSame($received->id, $byId[$receivedFrom->id]['friendship_id']);
        $this->assertSame('friends', $byId[$friend->id]['friendship_status']);
    }

    public function test_conversations_index_reports_other_user_friendship_status(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$a, $b] = $alice->id < $bob->id ? [$alice->id, $bob->id] : [$bob->id, $alice->id];
        Conversation::create(['user_one_id' => $a, 'user_two_id' => $b]);

        Sanctum::actingAs($alice);
        $response = $this->getJson('/api/conversations')->assertOk();

        $response->assertJsonPath('0.other_user.friendship_status', 'none');
    }
}
