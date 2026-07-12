<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationRaceTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();

        Friendship::create([
            'sender_id' => $this->alice->id,
            'recipient_id' => $this->bob->id,
            'status' => 'accepted',
        ]);
    }

    public function test_creating_conversation_for_pair_that_already_exists_returns_existing_conversation(): void
    {
        [$a, $b] = $this->alice->id < $this->bob->id
            ? [$this->alice->id, $this->bob->id]
            : [$this->bob->id, $this->alice->id];

        $existing = Conversation::create(['user_one_id' => $a, 'user_two_id' => $b]);

        Sanctum::actingAs($this->alice);

        $response = $this->postJson('/api/conversations', ['user_id' => $this->bob->id]);

        $response->assertStatus(201)->assertJsonPath('id', $existing->id);
        $this->assertSame(1, Conversation::count());
    }

    public function test_two_sequential_calls_for_new_pair_return_same_conversation(): void
    {
        // Covered by DirectMessageTest::test_creating_conversation_twice_returns_same_conversation.
        Sanctum::actingAs($this->alice);

        $first = $this->postJson('/api/conversations', ['user_id' => $this->bob->id]);
        $second = $this->postJson('/api/conversations', ['user_id' => $this->bob->id]);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, Conversation::count());
    }
}
