<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DirectMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private User $eve;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
        $this->eve = User::factory()->create();

        [$a, $b] = $this->alice->id < $this->bob->id
            ? [$this->alice->id, $this->bob->id]
            : [$this->bob->id, $this->alice->id];

        $this->conversation = Conversation::create([
            'user_one_id' => $a,
            'user_two_id' => $b,
        ]);
    }

    public function test_creating_conversation_twice_returns_same_conversation(): void
    {
        Sanctum::actingAs($this->alice);

        $first = $this->postJson('/api/conversations', ['user_id' => $this->bob->id]);
        $second = $this->postJson('/api/conversations', ['user_id' => $this->bob->id]);

        $first->assertStatus(201);
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, Conversation::count());
    }

    public function test_cannot_start_conversation_with_self(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson('/api/conversations', ['user_id' => $this->alice->id])
            ->assertStatus(422);
    }

    public function test_participant_can_send_and_fetch_messages(): void
    {
        // Sending now requires friendship (Task 7); this test predates that gate.
        Friendship::create(['sender_id' => $this->alice->id, 'recipient_id' => $this->bob->id, 'status' => 'accepted']);

        Sanctum::actingAs($this->alice);

        $this->postJson("/api/conversations/{$this->conversation->id}/messages", [
            'message' => 'Hello Bob!',
        ])->assertStatus(201)->assertJsonPath('message', 'Hello Bob!');

        $this->getJson("/api/conversations/{$this->conversation->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.message', 'Hello Bob!');
    }

    public function test_non_participant_cannot_read_or_send_messages(): void
    {
        Sanctum::actingAs($this->eve);

        $this->getJson("/api/conversations/{$this->conversation->id}/messages")
            ->assertStatus(403);

        $this->postJson("/api/conversations/{$this->conversation->id}/messages", [
            'message' => 'Intruder!',
        ])->assertStatus(403);
    }

    public function test_mark_read_clears_unread_count(): void
    {
        DirectMessage::create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->alice->id,
            'message' => 'Unread message',
        ]);

        Sanctum::actingAs($this->bob);

        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('0.unread_count', 1);

        $this->postJson("/api/conversations/{$this->conversation->id}/read")
            ->assertStatus(204);

        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('0.unread_count', 0);
    }

    public function test_conversations_list_includes_last_message(): void
    {
        DirectMessage::create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->alice->id,
            'message' => 'Latest message',
        ]);

        Sanctum::actingAs($this->bob);

        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('0.last_message.message', 'Latest message');
    }
}
