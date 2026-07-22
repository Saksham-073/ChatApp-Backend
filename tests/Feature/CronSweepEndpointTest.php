<?php

namespace Tests\Feature;

use App\Models\Call;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronSweepEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_token_triggers_sweep(): void
    {
        config(['services.cron.secret' => 'test-secret']);
        $call = Call::factory()->create([
            'status' => 'ringing',
            'created_at' => now()->subMinutes(2),
        ]);

        $this->getJson('/api/cron/sweep-calls?token=test-secret')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('missed', $call->fresh()->status);
    }

    public function test_invalid_token_is_rejected(): void
    {
        config(['services.cron.secret' => 'test-secret']);

        $this->getJson('/api/cron/sweep-calls?token=wrong')
            ->assertForbidden();
    }

    public function test_missing_secret_config_returns_server_error(): void
    {
        config(['services.cron.secret' => '']);

        $this->getJson('/api/cron/sweep-calls?token=anything')
            ->assertStatus(500);
    }
}
