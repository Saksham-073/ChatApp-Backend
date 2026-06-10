<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    public const EDIT_WINDOW_MINUTES = 15;

    protected $fillable = ['chat_room_id', 'user_id', 'message', 'edited_at', 'deleted_at'];

    protected $casts = ['edited_at' => 'datetime', 'deleted_at' => 'datetime'];

    public function room()
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
