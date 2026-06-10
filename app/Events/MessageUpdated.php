<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [new Channel('chat-room.'.$this->message->chat_room_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'chat_room_id' => $this->message->chat_room_id,
            'user_id' => $this->message->user_id,
            'message' => $this->message->message,
            'edited_at' => $this->message->edited_at,
            'deleted_at' => $this->message->deleted_at,
            'created_at' => $this->message->created_at,
            'user' => $this->message->user,
        ];
    }
}
