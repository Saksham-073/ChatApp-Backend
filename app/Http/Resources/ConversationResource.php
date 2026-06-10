<?php

namespace App\Http\Resources;

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

        return [
            'id' => $this->id,
            'other_user' => new UserResource($other),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'last_message' => $this->whenLoaded('latestMessage', fn () => [
                'message' => $this->latestMessage->message,
                'sender_id' => $this->latestMessage->sender_id,
                'created_at' => $this->latestMessage->created_at,
            ]),
        ];
    }
}
