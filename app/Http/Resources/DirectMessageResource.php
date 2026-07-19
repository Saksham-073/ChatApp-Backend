<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DirectMessageResource extends JsonResource
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
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'message' => $this->message,
            'nonce' => $this->nonce,
            'enc_version' => $this->enc_version,
            'edited_at' => $this->edited_at,
            'deleted_at' => $this->deleted_at,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'sender' => new UserResource($this->whenLoaded('sender')),
        ];
    }
}
