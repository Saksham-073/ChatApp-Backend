<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CallFactory extends Factory
{
    public function definition(): array
    {
        $caller = User::factory();
        $callee = User::factory();

        return [
            'conversation_id' => Conversation::factory(),
            'caller_id' => $caller,
            'callee_id' => $callee,
            'type' => 'video',
            'status' => 'ringing',
            'started_at' => now(),
        ];
    }

    public function configure(): static
    {
        // Keep conversation participants consistent with caller/callee
        return $this->afterMaking(function (\App\Models\Call $call) {
            $conv = Conversation::find($call->conversation_id);
            if ($conv) {
                $conv->update([
                    'user_one_id' => $call->caller_id,
                    'user_two_id' => $call->callee_id,
                ]);
            }
        });
    }
}
