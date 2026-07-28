<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DirectMessageFriendGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_stranger_cannot_start_a_new_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        Sanctum::actingAs($alice);

        $this->postJson('/api/conversations', ['user_id' => $bob->id])->assertStatus(403);
        $this->assertSame(0, Conversation::count());
    }

    public function test_friends_can_start_a_new_conversation_and_send_a_message(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'accepted']);
        Sanctum::actingAs($alice);

        $conv = $this->postJson('/api/conversations', ['user_id' => $bob->id])->assertStatus(201);
        $convId = $conv->json('id');

        $this->postJson("/api/conversations/{$convId}/messages", ['message' => 'Hey Bob!'])
            ->assertStatus(201);
    }

    public function test_existing_conversation_between_strangers_stays_readable_but_locked(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$a, $b] = $alice->id < $bob->id ? [$alice->id, $bob->id] : [$bob->id, $alice->id];
        $conversation = Conversation::create(['user_one_id' => $a, 'user_two_id' => $b]);

        Sanctum::actingAs($alice);

        // POST /conversations for the same pair returns the existing conversation, not 403,
        // because it's not creating anything new.
        $this->postJson('/api/conversations', ['user_id' => $bob->id])
            ->assertStatus(201)
            ->assertJsonPath('id', $conversation->id);

        // Reading history still works.
        $this->getJson("/api/conversations/{$conversation->id}/messages")->assertOk();

        // Sending is blocked until they're friends.
        $this->postJson("/api/conversations/{$conversation->id}/messages", ['message' => 'Hi'])
            ->assertStatus(403);
    }

    public function test_becoming_friends_unlocks_sending_in_a_pre_existing_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$a, $b] = $alice->id < $bob->id ? [$alice->id, $bob->id] : [$bob->id, $alice->id];
        $conversation = Conversation::create(['user_one_id' => $a, 'user_two_id' => $b]);
        Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'accepted']);

        Sanctum::actingAs($alice);

        $this->postJson("/api/conversations/{$conversation->id}/messages", ['message' => 'Hi'])
            ->assertStatus(201);
    }
}
