<?php

namespace Tests\Feature;

use App\Events\CallAccepted;
use App\Events\CallDeclined;
use App\Events\CallEnded;
use App\Events\CallInitiated;
use App\Events\CallMissed;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CallLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;
    private User $bob;
    private Conversation $conv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
        $this->conv = Conversation::factory()->create([
            'user_one_id' => $this->alice->id,
            'user_two_id' => $this->bob->id,
        ]);
        // Conversations require friendship elsewhere; calls only require conversation
        // participation, which implies friendship at creation time.
    }

    public function test_initiate_creates_ringing_call_and_broadcasts(): void
    {
        Event::fake([CallInitiated::class]);

        $res = $this->actingAs($this->alice)->postJson('/api/calls', [
            'conversation_id' => $this->conv->id,
            'type' => 'video',
        ]);

        $res->assertCreated()->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.callee_id', $this->bob->id);
        Event::assertDispatched(CallInitiated::class);
    }

    public function test_initiate_rejected_when_callee_busy(): void
    {
        Call::factory()->create([
            'conversation_id' => $this->conv->id,
            'caller_id' => $this->bob->id,
            'callee_id' => $this->alice->id,
            'status' => 'ongoing',
        ]);

        $this->actingAs($this->alice)->postJson('/api/calls', [
            'conversation_id' => $this->conv->id,
            'type' => 'audio',
        ])->assertStatus(409);
    }

    public function test_non_participant_cannot_initiate(): void
    {
        $eve = User::factory()->create();

        $this->actingAs($eve)->postJson('/api/calls', [
            'conversation_id' => $this->conv->id,
            'type' => 'audio',
        ])->assertForbidden();
    }

    private function ringingCall(): Call
    {
        return Call::factory()->create([
            'conversation_id' => $this->conv->id,
            'caller_id' => $this->alice->id,
            'callee_id' => $this->bob->id,
            'status' => 'ringing',
        ]);
    }

    public function test_callee_accepts(): void
    {
        Event::fake([CallAccepted::class]);
        $call = $this->ringingCall();

        $this->actingAs($this->bob)->postJson("/api/calls/{$call->id}/accept")
            ->assertOk()->assertJsonPath('data.status', 'ongoing');
        $this->assertNotNull($call->fresh()->answered_at);
        Event::assertDispatched(CallAccepted::class);
    }

    public function test_caller_cannot_accept_own_call(): void
    {
        $call = $this->ringingCall();

        $this->actingAs($this->alice)->postJson("/api/calls/{$call->id}/accept")
            ->assertForbidden();
    }

    public function test_callee_declines(): void
    {
        Event::fake([CallDeclined::class]);
        $call = $this->ringingCall();

        $this->actingAs($this->bob)->postJson("/api/calls/{$call->id}/decline")->assertOk();
        $this->assertSame('declined', $call->fresh()->status);
        Event::assertDispatched(CallDeclined::class);
    }

    public function test_caller_timeout_marks_missed_and_broadcasts_missed(): void
    {
        Event::fake([CallEnded::class, CallMissed::class]);
        $call = $this->ringingCall();

        $this->actingAs($this->alice)->postJson("/api/calls/{$call->id}/end", ['reason' => 'timeout'])
            ->assertOk();
        $this->assertSame('missed', $call->fresh()->status);
        Event::assertDispatched(CallEnded::class);
        Event::assertDispatched(CallMissed::class);
    }

    public function test_participant_ends_ongoing_call(): void
    {
        Event::fake([CallEnded::class]);
        $call = $this->ringingCall();
        $call->update(['status' => 'ongoing', 'answered_at' => now()]);

        $this->actingAs($this->bob)->postJson("/api/calls/{$call->id}/end")->assertOk();
        $fresh = $call->fresh();
        $this->assertSame('ended', $fresh->status);
        $this->assertNotNull($fresh->ended_at);
        Event::assertDispatched(CallEnded::class);
    }

    public function test_stranger_cannot_touch_call(): void
    {
        $eve = User::factory()->create();
        $call = $this->ringingCall();

        $this->actingAs($eve)->postJson("/api/calls/{$call->id}/end")->assertForbidden();
    }

    public function test_accept_conflicts_when_not_ringing(): void
    {
        $call = $this->ringingCall();
        $call->update(['status' => 'ended']);

        $this->actingAs($this->bob)->postJson("/api/calls/{$call->id}/accept")
            ->assertStatus(409);
    }
}
