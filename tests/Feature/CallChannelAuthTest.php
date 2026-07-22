<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Set pusher broadcaster BEFORE app boots so auth path is wired correctly
        $_SERVER['BROADCAST_CONNECTION'] = $_ENV['BROADCAST_CONNECTION'] = 'pusher';
        $_SERVER['PUSHER_APP_KEY'] = $_ENV['PUSHER_APP_KEY'] = 'test';
        $_SERVER['PUSHER_APP_SECRET'] = $_ENV['PUSHER_APP_SECRET'] = 'test';
        $_SERVER['PUSHER_APP_ID'] = $_ENV['PUSHER_APP_ID'] = '1';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Restore null broadcaster for other tests
        $_SERVER['BROADCAST_CONNECTION'] = $_ENV['BROADCAST_CONNECTION'] = 'null';

        parent::tearDown();
    }

    private function authAttempt(User $user, int $callId)
    {
        return $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => "private-call.{$callId}",
            'socket_id' => '123.456',
        ]);
    }

    public function test_participants_may_join_call_channel(): void
    {
        $call = Call::factory()->create();

        $this->authAttempt($call->caller, $call->id)->assertOk();
        $this->authAttempt($call->callee, $call->id)->assertOk();
    }

    public function test_stranger_may_not_join_call_channel(): void
    {
        $call = Call::factory()->create();
        $stranger = User::factory()->create();

        $this->authAttempt($stranger, $call->id)->assertForbidden();
    }
}
