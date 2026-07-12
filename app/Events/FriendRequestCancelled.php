<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendRequestCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $friendshipId, public int $notifyUserId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->notifyUserId)];
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->friendshipId];
    }
}
