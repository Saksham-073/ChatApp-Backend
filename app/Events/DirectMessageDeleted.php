<?php

namespace App\Events;

use App\Models\DirectMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DirectMessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DirectMessage $dm) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->dm->conversation_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->dm->id,
            'conversation_id' => $this->dm->conversation_id,
            'deleted_at' => $this->dm->deleted_at,
        ];
    }
}
