<?php

namespace App\Events;

use App\Models\Friendship;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendRequestSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Friendship $friendship) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->friendship->recipient_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->friendship->id,
            'status' => $this->friendship->status,
            'sender' => $this->friendship->sender,
            'created_at' => $this->friendship->created_at,
        ];
    }
}
