<?php

namespace Tests\Feature;

use App\Events\CallEnded;
use App\Events\CallMissed;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CallAuxiliaryTest extends TestCase
{
    use RefreshDatabase;

    public function createCall(array $attrs = []): Call
    {
        return Call::factory()->create($attrs);
    }

    public function test_heartbeat_updates_last_seen_at(): void
    {
        $call = $this->createCall(['status' => 'ongoing', 'last_seen_at' => now()->subMinutes(5)]);

        $this->actingAs($call->caller)->postJson("/api/calls/{$call->id}/heartbeat")
            ->assertNoContent();
        $this->assertTrue($call->fresh()->last_seen_at->gt(now()->subMinute()));
    }

    public function test_seen_marks_missed_call(): void
    {
        $call = $this->createCall(['status' => 'missed']);

        $this->actingAs($call->callee)->postJson("/api/calls/{$call->id}/seen")->assertNoContent();
        $this->assertNotNull($call->fresh()->seen_at);
    }

    public function test_caller_cannot_mark_seen(): void
    {
        $call = $this->createCall(['status' => 'missed']);

        $this->actingAs($call->caller)->postJson("/api/calls/{$call->id}/seen")->assertForbidden();
    }

    public function test_missed_list_returns_only_unseen_missed_for_me(): void
    {
        $me = User::factory()->create();
        $this->createCall(['callee_id' => $me->id, 'status' => 'missed']);
        $this->createCall(['callee_id' => $me->id, 'status' => 'missed', 'seen_at' => now()]);
        $this->createCall(['caller_id' => $me->id, 'status' => 'missed']); // I was caller — not mine to see
        $this->createCall(['status' => 'missed']); // someone else's

        $this->actingAs($me)->getJson('/api/calls/missed')
            ->assertOk()->assertJsonCount(1);
    }

    public function test_history_returns_final_calls_for_participants_only(): void
    {
        $call = $this->createCall(['status' => 'ended', 'ended_at' => now()]);
        $this->createCall([
            'conversation_id' => $call->conversation_id,
            'caller_id' => $call->caller_id,
            'callee_id' => $call->callee_id,
            'status' => 'ringing',
        ]);
        $eve = User::factory()->create();

        $this->actingAs($call->caller)
            ->getJson("/api/conversations/{$call->conversation_id}/calls")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($eve)
            ->getJson("/api/conversations/{$call->conversation_id}/calls")
            ->assertForbidden();
    }

    public function test_ice_servers_returns_stun(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/ice-servers')
            ->assertOk()
            ->assertJsonPath('iceServers.0.urls', 'stun:stun.l.google.com:19302');
    }

    public function test_sweep_marks_stale_ringing_missed_and_stale_ongoing_ended(): void
    {
        Event::fake([CallMissed::class, CallEnded::class]);
        $staleRinging = $this->createCall(['status' => 'ringing', 'created_at' => now()->subMinutes(2)]);
        $staleOngoing = $this->createCall(['status' => 'ongoing', 'last_seen_at' => now()->subMinutes(3)]);
        $freshRinging = $this->createCall(['status' => 'ringing']);
        $freshOngoing = $this->createCall(['status' => 'ongoing', 'last_seen_at' => now()]);

        $this->artisan('calls:sweep')->assertSuccessful();

        $this->assertSame('missed', $staleRinging->fresh()->status);
        $this->assertSame('ended', $staleOngoing->fresh()->status);
        $this->assertSame('ringing', $freshRinging->fresh()->status);
        $this->assertSame('ongoing', $freshOngoing->fresh()->status);
        Event::assertDispatched(CallMissed::class, 1);
        Event::assertDispatched(CallEnded::class, 2);
    }
}
