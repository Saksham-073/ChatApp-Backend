<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendRemoved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $userId, public int $otherUserId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->otherUserId)];
    }

    public function broadcastWith(): array
    {
        return ['user_id' => $this->userId];
    }
}
