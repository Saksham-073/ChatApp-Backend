<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $res = $this->getJson('/api/users')->assertOk();
        $bobRow = collect($res->json())->firstWhere('id', $bob->id);
        $this->assertArrayNotHasKey('encrypted_private_key', $bobRow);
        $this->assertArrayNotHasKey('key_salt', $bobRow);
        $this->assertArrayNotHasKey('key_nonce', $bobRow);
    }
}
