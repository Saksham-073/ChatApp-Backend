<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private ChatRoom $room;

    private ChatMessage $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
        $this->room = ChatRoom::create(['name' => 'General']);
        $this->message = ChatMessage::create([
            'chat_room_id' => $this->room->id,
            'user_id' => $this->alice->id,
            'message' => 'Original text',
        ]);
    }

    public function test_sender_can_edit_message_within_window(): void
    {
        Sanctum::actingAs($this->alice);

        $this->patchJson("/api/chat/room/{$this->room->id}/messages/{$this->message->id}", [
            'message' => 'Updated text',
        ])->assertOk()
            ->assertJsonPath('message', 'Updated text');

        $this->assertNotNull($this->message->fresh()->edited_at);
    }

    public function test_edit_rejected_after_window_expires(): void
    {
        Sanctum::actingAs($this->alice);
        $this->travel(ChatMessage::EDIT_WINDOW_MINUTES + 1)->minutes();

        $this->patchJson("/api/chat/room/{$this->room->id}/messages/{$this->message->id}", [
            'message' => 'Too late',
        ])->assertStatus(403);
    }

    public function test_non_sender_cannot_edit(): void
    {
        Sanctum::actingAs($this->bob);

        $this->patchJson("/api/chat/room/{$this->room->id}/messages/{$this->message->id}", [
            'message' => 'Hijacked',
        ])->assertStatus(403);
    }

    public function test_editing_deleted_message_fails(): void
    {
        $this->message->update(['message' => '', 'deleted_at' => now()]);
        Sanctum::actingAs($this->alice);

        $this->patchJson("/api/chat/room/{$this->room->id}/messages/{$this->message->id}", [
            'message' => 'Resurrect',
        ])->assertStatus(409);
    }

    public function test_message_must_belong_to_room_in_url(): void
    {
        $otherRoom = ChatRoom::create(['name' => 'Other']);
        Sanctum::actingAs($this->alice);

        $this->patchJson("/api/chat/room/{$otherRoom->id}/messages/{$this->message->id}", [
            'message' => 'Wrong room',
        ])->assertStatus(404);
    }

    public function test_sender_can_delete_anytime_and_content_is_blanked(): void
    {
        Sanctum::actingAs($this->alice);
        $this->travel(2)->days();

        $this->deleteJson("/api/chat/room/{$this->room->id}/messages/{$this->message->id}")
            ->assertStatus(204);

        $fresh = $this->message->fresh();
        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame('', $fresh->message);
    }

    public function test_delete_is_idempotent(): void
    {
        Sanctum::actingAs($this->alice);

        $this->deleteJson("/api/chat/room/{$this->room->id}/messages/{$this->message->id}")
            ->assertStatus(204);
        $this->deleteJson("/api/chat/room/{$this->room->id}/messages/{$this->message->id}")
            ->assertStatus(204);
    }

    public function test_non_sender_cannot_delete(): void
    {
        Sanctum::actingAs($this->bob);

        $this->deleteJson("/api/chat/room/{$this->room->id}/messages/{$this->message->id}")
            ->assertStatus(403);
    }
}
