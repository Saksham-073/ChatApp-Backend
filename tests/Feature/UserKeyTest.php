<?php

namespace Tests\Feature;

use App\Models\User;
use App\Events\UserKeysChanged;
use App\Models\Conversation;
use App\Models\ConversationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserKeyTest extends TestCase
{
    use RefreshDatabase;

    private array $payload = [
        'public_key' => 'cHViLWtleS1iYXNlNjQ=',
        'encrypted_private_key' => 'ZW5jcnlwdGVkLXByaXYta2V5',
        'key_salt' => 'c2FsdA==',
        'key_nonce' => 'bm9uY2U=',
    ];

    public function test_unenrolled_user_gets_404_on_key_fetch(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/me/keys')->assertStatus(404);
    }

    public function test_user_can_enroll_and_fetch_keys(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/me/keys', $this->payload)
            ->assertStatus(201)
            ->assertJson($this->payload);

        $this->getJson('/api/me/keys')
            ->assertOk()
            ->assertJson($this->payload);
    }

    public function test_re_enrollment_is_rejected_with_409(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/me/keys', $this->payload)->assertStatus(201);
        $this->postJson('/api/me/keys', $this->payload)->assertStatus(409);
    }

    public function test_passphrase_change_replaces_blob_but_rejects_public_key_change(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/me/keys', $this->payload)->assertStatus(201);

        $newBlob = [
            'encrypted_private_key' => 'bmV3LWJsb2I=',
            'key_salt' => 'bmV3LXNhbHQ=',
            'key_nonce' => 'bmV3LW5vbmNl',
        ];

        $this->patchJson('/api/me/keys', $newBlob)
            ->assertOk()
            ->assertJsonPath('encrypted_private_key', 'bmV3LWJsb2I=')
            ->assertJsonPath('public_key', $this->payload['public_key']);

        $this->patchJson('/api/me/keys', [...$newBlob, 'public_key' => 'YXR0YWNrZXItcHVi'])
            ->assertStatus(422);
    }

    public function test_key_blob_fields_are_hidden_from_user_listings(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bob->update([...$this->payload]);

        Sanctum::actingAs($alice);

        // Check /api/users (UserResource filtered view)
        $res = $this->getJson('/api/users')->assertOk();
        $bobRow = collect($res->json())->firstWhere('id', $bob->id);
        $this->assertArrayNotHasKey('encrypted_private_key', $bobRow);
        $this->assertArrayNotHasKey('key_salt', $bobRow);
        $this->assertArrayNotHasKey('key_nonce', $bobRow);
    }

    public function test_key_blob_fields_are_hidden_from_authenticated_user_me(): void
    {
        $user = User::factory()->create();
        $user->update([...$this->payload]);

        Sanctum::actingAs($user);

        // Check /api/me (raw model serialization) — exercises #[Hidden] attribute
        $res = $this->getJson('/api/me')->assertOk();
        $data = $res->json();

        // public_key SHOULD be present (not hidden)
        $this->assertArrayHasKey('public_key', $data);
        $this->assertEquals($this->payload['public_key'], $data['public_key']);

        // Sensitive fields MUST be hidden
        $this->assertArrayNotHasKey('encrypted_private_key', $data);
        $this->assertArrayNotHasKey('key_salt', $data);
        $this->assertArrayNotHasKey('key_nonce', $data);
    }

    public function test_reset_replaces_keys_deletes_wraps_and_notifies_peers(): void
    {
        Event::fake([UserKeysChanged::class]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$a, $b] = $alice->id < $bob->id ? [$alice->id, $bob->id] : [$bob->id, $alice->id];
        $conversation = Conversation::create(['user_one_id' => $a, 'user_two_id' => $b]);

        Sanctum::actingAs($alice);
        $this->postJson('/api/me/keys', $this->payload)->assertStatus(201);

        ConversationKey::create(['conversation_id' => $conversation->id, 'user_id' => $alice->id, 'wrapped_key' => 'd3JhcC1hbGljZQ==']);
        ConversationKey::create(['conversation_id' => $conversation->id, 'user_id' => $bob->id, 'wrapped_key' => 'd3JhcC1ib2I=']);

        $newKeys = [
            'public_key' => 'bmV3LXB1Yg==',
            'encrypted_private_key' => 'bmV3LWJsb2I=',
            'key_salt' => 'bmV3LXNhbHQ=',
            'key_nonce' => 'bmV3LW5vbmNl',
        ];

        $this->postJson('/api/me/keys/reset', $newKeys)
            ->assertOk()
            ->assertJsonPath('public_key', 'bmV3LXB1Yg==');

        // Alice's wrap deleted, Bob's intact
        $this->assertSame(0, ConversationKey::where('user_id', $alice->id)->count());
        $this->assertSame(1, ConversationKey::where('user_id', $bob->id)->count());

        Event::assertDispatched(UserKeysChanged::class, function (UserKeysChanged $event) use ($alice, $bob) {
            return $event->userId === $alice->id
                && $event->publicKey === 'bmV3LXB1Yg=='
                && $event->peerIds === [$bob->id];
        });
    }

    public function test_reset_requires_prior_enrollment(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/me/keys/reset', $this->payload)->assertStatus(404);
    }
}
