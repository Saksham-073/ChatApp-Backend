<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DirectMessageEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private User $eve;

    private Conversation $conversation;

    private DirectMessage $dm;

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

        $this->dm = DirectMessage::create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->alice->id,
            'message' => 'Original DM',
        ]);
    }

    public function test_sender_can_edit_dm_within_window(): void
    {
        Sanctum::actingAs($this->alice);

        $this->patchJson("/api/conversations/{$this->conversation->id}/messages/{$this->dm->id}", [
            'message' => 'Edited DM',
        ])->assertOk()
            ->assertJsonPath('message', 'Edited DM');

        $this->assertNotNull($this->dm->fresh()->edited_at);
    }

    public function test_edit_rejected_after_window(): void
    {
        Sanctum::actingAs($this->alice);
        $this->travel(DirectMessage::EDIT_WINDOW_MINUTES + 1)->minutes();

        $this->patchJson("/api/conversations/{$this->conversation->id}/messages/{$this->dm->id}", [
            'message' => 'Too late',
        ])->assertStatus(403);
    }

    public function test_recipient_cannot_edit_or_delete(): void
    {
        Sanctum::actingAs($this->bob);

        $this->patchJson("/api/conversations/{$this->conversation->id}/messages/{$this->dm->id}", [
            'message' => 'Not mine',
        ])->assertStatus(403);

        $this->deleteJson("/api/conversations/{$this->conversation->id}/messages/{$this->dm->id}")
            ->assertStatus(403);
    }

    public function test_non_participant_cannot_edit_or_delete(): void
    {
        Sanctum::actingAs($this->eve);

        $this->patchJson("/api/conversations/{$this->conversation->id}/messages/{$this->dm->id}", [
            'message' => 'Intruder',
        ])->assertStatus(403);

        $this->deleteJson("/api/conversations/{$this->conversation->id}/messages/{$this->dm->id}")
            ->assertStatus(403);
    }

    public function test_sender_can_delete_and_content_is_blanked(): void
    {
        Sanctum::actingAs($this->alice);
        $this->travel(3)->days();

        $this->deleteJson("/api/conversations/{$this->conversation->id}/messages/{$this->dm->id}")
            ->assertStatus(204);

        $fresh = $this->dm->fresh();
        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame('', $fresh->message);
    }

    public function test_deleted_latest_message_blanks_conversation_preview(): void
    {
        Sanctum::actingAs($this->alice);
        $this->deleteJson("/api/conversations/{$this->conversation->id}/messages/{$this->dm->id}")
            ->assertStatus(204);

        Sanctum::actingAs($this->bob);
        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('0.last_message.message', '')
            ->assertJsonPath('0.last_message.id', $this->dm->id);
    }

    public function test_message_must_belong_to_conversation_in_url(): void
    {
        [$a, $b] = $this->alice->id < $this->eve->id
            ? [$this->alice->id, $this->eve->id]
            : [$this->eve->id, $this->alice->id];

        $otherConversation = Conversation::create([
            'user_one_id' => $a,
            'user_two_id' => $b,
        ]);

        Sanctum::actingAs($this->alice);

        $this->patchJson("/api/conversations/{$otherConversation->id}/messages/{$this->dm->id}", [
            'message' => 'Wrong conversation',
        ])->assertStatus(404);
    }

    public function test_editing_deleted_dm_fails(): void
    {
        $this->dm->update(['message' => '', 'deleted_at' => now()]);
        Sanctum::actingAs($this->alice);

        $this->patchJson("/api/conversations/{$this->conversation->id}/messages/{$this->dm->id}", [
            'message' => 'Resurrect',
        ])->assertStatus(409);
    }

    public function test_dm_delete_is_idempotent(): void
    {
        Sanctum::actingAs($this->alice);

        $this->deleteJson("/api/conversations/{$this->conversation->id}/messages/{$this->dm->id}")
            ->assertStatus(204);
        $this->deleteJson("/api/conversations/{$this->conversation->id}/messages/{$this->dm->id}")
            ->assertStatus(204);
    }
}
