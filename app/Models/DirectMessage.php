<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectMessage extends Model
{
    public const EDIT_WINDOW_MINUTES = 15;

    protected $fillable = ['conversation_id', 'sender_id', 'message', 'read_at', 'edited_at', 'deleted_at', 'nonce', 'enc_version'];

    protected $casts = ['read_at' => 'datetime', 'edited_at' => 'datetime', 'deleted_at' => 'datetime', 'enc_version' => 'integer'];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
