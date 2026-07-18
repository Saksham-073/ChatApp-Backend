<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationKey extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'key_version', 'wrapped_key'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
