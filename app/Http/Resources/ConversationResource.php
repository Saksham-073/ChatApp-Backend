<?php

namespace App\Http\Resources;

use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userId = $request->user()->id;
        $other = $this->user_one_id === $userId ? $this->userTwo : $this->userOne;
        $rel = Friendship::statusBetween($userId, $other->id);

        return [
            'id' => $this->id,
            'other_user' => [
                ...(new UserResource($other))->resolve(),
                'friendship_status' => $rel['status'],
                'friendship_id' => $rel['id'],
            ],
            'unread_count' => (int) ($this->unread_count ?? 0),
            'last_message' => $this->whenLoaded('latestMessage', fn () => [
                'id' => $this->latestMessage->id,
                'message' => $this->latestMessage->message,
                'sender_id' => $this->latestMessage->sender_id,
                'created_at' => $this->latestMessage->created_at,
                'deleted_at' => $this->latestMessage->deleted_at,
            ]),
        ];
    }
}
