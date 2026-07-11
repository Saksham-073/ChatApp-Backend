<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FriendshipResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'sender' => new UserResource($this->whenLoaded('sender')),
            'recipient' => new UserResource($this->whenLoaded('recipient')),
            'created_at' => $this->created_at,
        ];
    }
}
