<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class UserKeysChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param int[] $peerIds */
    public function __construct(public int $userId, public string $publicKey, public array $peerIds) {}

    public function broadcastOn(): array
    {
        return array_map(fn (int $id) => new PrivateChannel('user.'.$id), $this->peerIds);
    }

    public function broadcastWith(): array
    {
        return ['user_id' => $this->userId, 'public_key' => $this->publicKey];
    }
}
