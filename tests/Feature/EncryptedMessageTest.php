<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EncryptedMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['public_key' => 'YWxpY2UtcHVi']);
        $this->bob = User::factory()->create(['public_key' => 'Ym9iLXB1Yg==']);
        Friendship::create(['sender_id' => $this->alice->id, 'recipient_id' => $this->bob->id, 'status' => 'accepted']);

        [$a, $b] = $this->alice->id < $this->bob->id
            ? [$this->alice->id, $this->bob->id]
            : [$this->bob->id, $this->alice->id];
        $this->conversation = Conversation::create(['user_one_id' => $a, 'user_two_id' => $b]);
    }

    private function url(): string
    {
        return "/api/conversations/{$this->conversation->id}/messages";
    }

    public function test_encrypted_message_roundtrip_stores_nonce_and_version(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson($this->url(), [
            'message' => 'Y2lwaGVydGV4dA==',
            'nonce' => 'bm9uY2UtMjRieXRl',
            'enc_version' => 1,
        ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Y2lwaGVydGV4dA==')
            ->assertJsonPath('nonce', 'bm9uY2UtMjRieXRl')
            ->assertJsonPath('enc_version', 1);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.0.enc_version', 1)
            ->assertJsonPath('data.0.nonce', 'bm9uY2UtMjRieXRl');
    }

    public function test_plaintext_message_defaults_to_version_zero(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson($this->url(), ['message' => 'hello'])
            ->assertStatus(201)
            ->assertJsonPath('enc_version', 0)
            ->assertJsonPath('nonce', null);
    }

    public function test_encrypted_message_without_nonce_rejected(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson($this->url(), ['message' => 'Y2lwaGVydGV4dA==', 'enc_version' => 1])
            ->assertStatus(422);
    }

    public function test_plaintext_message_with_nonce_rejected(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson($this->url(), ['message' => 'hello', 'nonce' => 'bm9uY2U=', 'enc_version' => 0])
            ->assertStatus(422);
    }

    public function test_edit_cannot_downgrade_encrypted_message_to_plaintext(): void
    {
        Sanctum::actingAs($this->alice);

        $id = $this->postJson($this->url(), [
            'message' => 'Y2lwaGVydGV4dA==',
            'nonce' => 'bm9uY2UtMjRieXRl',
            'enc_version' => 1,
        ])->json('id');

        $this->patchJson("{$this->url()}/{$id}", ['message' => 'now plaintext', 'enc_version' => 0])
            ->assertStatus(422);
    }

    public function test_edit_can_upgrade_plaintext_message_to_encrypted(): void
    {
        Sanctum::actingAs($this->alice);

        $id = $this->postJson($this->url(), ['message' => 'hello'])->json('id');

        $this->patchJson("{$this->url()}/{$id}", [
            'message' => 'Y2lwaGVydGV4dA==',
            'nonce' => 'bm9uY2UtMjRieXRl',
            'enc_version' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('enc_version', 1);
    }

    public function test_plaintext_message_over_2000_chars_rejected(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson($this->url(), ['message' => str_repeat('a', 2001)])
            ->assertStatus(422);
    }

    public function test_encrypted_message_cap_is_4096(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson($this->url(), [
            'message' => str_repeat('a', 4096),
            'nonce' => 'bm9uY2UtMjRieXRl',
            'enc_version' => 1,
        ])->assertStatus(201);

        $this->postJson($this->url(), [
            'message' => str_repeat('a', 4097),
            'nonce' => 'bm9uY2UtMjRieXRl',
            'enc_version' => 1,
        ])->assertStatus(422);
    }

    public function test_unknown_enc_version_rejected(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson($this->url(), ['message' => 'x', 'enc_version' => 2])
            ->assertStatus(422);
    }

    public function test_users_listing_exposes_public_key(): void
    {
        Sanctum::actingAs($this->alice);

        $res = $this->getJson('/api/users')->assertOk();
        $bobRow = collect($res->json())->firstWhere('id', $this->bob->id);
        $this->assertSame('Ym9iLXB1Yg==', $bobRow['public_key']);
    }

    public function test_conversation_payload_includes_peer_public_key_and_last_message_crypto_fields(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson($this->url(), [
            'message' => 'Y2lwaGVydGV4dA==',
            'nonce' => 'bm9uY2UtMjRieXRl',
            'enc_version' => 1,
        ])->assertStatus(201);

        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('0.other_user.public_key', 'Ym9iLXB1Yg==')
            ->assertJsonPath('0.last_message.enc_version', 1)
            ->assertJsonPath('0.last_message.nonce', 'bm9uY2UtMjRieXRl');
    }
}
