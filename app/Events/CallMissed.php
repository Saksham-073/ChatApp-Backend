<?php

namespace App\Events;

use App\Http\Resources\CallResource;
use App\Models\Call;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallMissed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Call $call)
    {
        $this->call->load(['caller', 'callee']);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->call->callee_id)];
    }

    public function broadcastWith(): array
    {
        return ['call' => (new CallResource($this->call))->resolve()];
    }
}
