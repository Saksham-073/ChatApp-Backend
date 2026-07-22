<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    use HasFactory;

    public const TYPES = ['audio', 'video'];

    public const STATUSES = ['ringing', 'ongoing', 'ended', 'declined', 'missed', 'failed'];

    protected $fillable = [
        'conversation_id', 'caller_id', 'callee_id', 'type', 'status',
        'started_at', 'answered_at', 'ended_at', 'seen_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function caller()
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function callee()
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function isParticipant(int $userId): bool
    {
        return $this->caller_id === $userId || $this->callee_id === $userId;
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['ringing', 'ongoing']);
    }
}
