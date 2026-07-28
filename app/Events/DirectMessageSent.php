<?php

namespace App\Events;

use App\Models\DirectMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DirectMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DirectMessage $dm) {}

    public function broadcastOn(): array
    {
        $conversation = $this->dm->conversation;
        $recipientId = $conversation->user_one_id === $this->dm->sender_id
            ? $conversation->user_two_id
            : $conversation->user_one_id;

        return [
            new PrivateChannel('conversation.'.$this->dm->conversation_id),
            // Recipient's personal channel, which reaches them even when they have
            // never seen this conversation and so aren't subscribed to it yet
            new PrivateChannel('user.'.$recipientId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->dm->id,
            'conversation_id' => $this->dm->conversation_id,
            'sender_id' => $this->dm->sender_id,
            'message' => $this->dm->message,
            'nonce' => $this->dm->nonce,
            'enc_version' => $this->dm->enc_version,
            'created_at' => $this->dm->created_at,
            'sender' => $this->dm->sender,
        ];
    }
}
