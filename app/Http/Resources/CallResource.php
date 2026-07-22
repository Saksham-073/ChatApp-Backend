<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'caller_id' => $this->caller_id,
            'callee_id' => $this->callee_id,
            'type' => $this->type,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'answered_at' => $this->answered_at,
            'ended_at' => $this->ended_at,
            'seen_at' => $this->seen_at,
            'caller' => ['id' => $this->caller->id, 'name' => $this->caller->name],
            'callee' => ['id' => $this->callee->id, 'name' => $this->callee->name],
        ];
    }
}
