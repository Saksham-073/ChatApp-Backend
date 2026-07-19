<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationKeyTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private User $eve;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['public_key' => 'YWxpY2UtcHVi']);
        $this->bob = User::factory()->create(['public_key' => 'Ym9iLXB1Yg==']);
        $this->eve = User::factory()->create(['public_key' => 'ZXZlLXB1Yg==']);

        [$a, $b] = $this->alice->id < $this->bob->id
            ? [$this->alice->id, $this->bob->id]
            : [$this->bob->id, $this->alice->id];

        $this->conversation = Conversation::create(['user_one_id' => $a, 'user_two_id' => $b]);
    }

    private function bothWraps(): array
    {
        return ['keys' => [
            ['user_id' => $this->alice->id, 'wrapped_key' => 'd3JhcC1hbGljZQ=='],
            ['user_id' => $this->bob->id, 'wrapped_key' => 'd3JhcC1ib2I='],
        ]];
    }

    public function test_participant_can_store_wraps_for_both_members(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson("/api/conversations/{$this->conversation->id}/keys", $this->bothWraps())
            ->assertStatus(201);

        $this->assertSame(2, ConversationKey::where('conversation_id', $this->conversation->id)->count());
    }

    public function test_non_participant_cannot_store_wraps(): void
    {
        Sanctum::actingAs($this->eve);

        $this->postJson("/api/conversations/{$this->conversation->id}/keys", $this->bothWraps())
            ->assertStatus(403);
    }

    public function test_initial_creation_must_cover_both_participants(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson("/api/conversations/{$this->conversation->id}/keys", ['keys' => [
            ['user_id' => $this->alice->id, 'wrapped_key' => 'd3JhcC1hbGljZQ=='],
        ]])->assertStatus(422);
    }

    public function test_wraps_for_non_participant_rejected(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson("/api/conversations/{$this->conversation->id}/keys", ['keys' => [
            ['user_id' => $this->alice->id, 'wrapped_key' => 'd3JhcC1hbGljZQ=='],
            ['user_id' => $this->eve->id, 'wrapped_key' => 'd3JhcC1ldmU='],
        ]])->assertStatus(422);
    }

    public function test_duplicate_submission_race_returns_409(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson("/api/conversations/{$this->conversation->id}/keys", $this->bothWraps())
            ->assertStatus(201);
        $this->postJson("/api/conversations/{$this->conversation->id}/keys", $this->bothWraps())
            ->assertStatus(409);
    }

    public function test_partial_rewrap_after_reset_is_allowed(): void
    {
        Sanctum::actingAs($this->alice);
        $this->postJson("/api/conversations/{$this->conversation->id}/keys", $this->bothWraps())
            ->assertStatus(201);

        // Simulate bob's reset: his wrap row was deleted
        ConversationKey::where('user_id', $this->bob->id)->delete();

        // Alice re-wraps the CK for bob's new key — single-entry insert succeeds
        $this->postJson("/api/conversations/{$this->conversation->id}/keys", ['keys' => [
            ['user_id' => $this->bob->id, 'wrapped_key' => 'bmV3LXdyYXAtYm9i'],
        ]])->assertStatus(201);

        $this->assertSame(2, ConversationKey::where('conversation_id', $this->conversation->id)->count());
    }

    public function test_user_can_list_own_wraps_only(): void
    {
        ConversationKey::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->alice->id,
            'wrapped_key' => 'd3JhcC1hbGljZQ==',
        ]);
        ConversationKey::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->bob->id,
            'wrapped_key' => 'd3JhcC1ib2I=',
        ]);

        Sanctum::actingAs($this->alice);

        $this->getJson('/api/me/conversation-keys')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.conversation_id', $this->conversation->id)
            ->assertJsonPath('0.wrapped_key', 'd3JhcC1hbGljZQ==');
    }

    public function test_existing_peer_wrap_cannot_be_overwritten(): void
    {
        Sanctum::actingAs($this->alice);
        $this->postJson("/api/conversations/{$this->conversation->id}/keys", $this->bothWraps())
            ->assertStatus(201);

        $original = ConversationKey::where('user_id', $this->bob->id)->value('wrapped_key');

        // Attacker-style resubmission: a different wrap for a peer who already has one
        $this->postJson("/api/conversations/{$this->conversation->id}/keys", ['keys' => [
            ['user_id' => $this->bob->id, 'wrapped_key' => 'QVRUQUNLRVItd3JhcA=='],
        ]])->assertStatus(409);

        $this->assertSame(
            $original,
            ConversationKey::where('user_id', $this->bob->id)->value('wrapped_key'),
            'A stored peer wrap must never be overwritten.'
        );
    }
}
