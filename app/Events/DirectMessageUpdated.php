<?php

namespace App\Events;

use App\Models\DirectMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DirectMessageUpdated implements ShouldBroadcast
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
            'sender_id' => $this->dm->sender_id,
            'message' => $this->dm->message,
            'edited_at' => $this->dm->edited_at,
            'deleted_at' => $this->dm->deleted_at,
            'created_at' => $this->dm->created_at,
            'sender' => $this->dm->sender,
        ];
    }
}
