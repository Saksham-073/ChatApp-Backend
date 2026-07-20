<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_belongs_to_conversation_and_users(): void
    {
        $call = Call::factory()->create();

        $this->assertInstanceOf(Conversation::class, $call->conversation);
        $this->assertInstanceOf(User::class, $call->caller);
        $this->assertInstanceOf(User::class, $call->callee);
        $this->assertSame('ringing', $call->status);
    }

    public function test_is_participant(): void
    {
        $call = Call::factory()->create();
        $stranger = User::factory()->create();

        $this->assertTrue($call->isParticipant($call->caller_id));
        $this->assertTrue($call->isParticipant($call->callee_id));
        $this->assertFalse($call->isParticipant($stranger->id));
    }

    public function test_active_scope_only_returns_ringing_and_ongoing(): void
    {
        Call::factory()->create(['status' => 'ringing']);
        Call::factory()->create(['status' => 'ongoing']);
        Call::factory()->create(['status' => 'ended']);
        Call::factory()->create(['status' => 'missed']);

        $this->assertSame(2, Call::active()->count());
    }
}
