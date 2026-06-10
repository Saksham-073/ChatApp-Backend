<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatRoom extends Model
{
    protected $fillable = ['name'];

    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }
}
