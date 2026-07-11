<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Friendship extends Model
{
    protected $fillable = ['sender_id', 'recipient_id', 'status'];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Map of otherUserId => ['status' => ..., 'id' => friendshipId] for every
     * friendship row involving $viewerId. Users with no row are simply absent.
     */
    public static function statusMapFor(int $viewerId): array
    {
        return static::query()
            ->where('sender_id', $viewerId)
            ->orWhere('recipient_id', $viewerId)
            ->get()
            ->mapWithKeys(function (Friendship $f) use ($viewerId) {
                $otherId = $f->sender_id === $viewerId ? $f->recipient_id : $f->sender_id;
                $status = match (true) {
                    $f->status === 'accepted' => 'friends',
                    $f->sender_id === $viewerId => 'pending_sent',
                    default => 'pending_received',
                };

                return [$otherId => ['status' => $status, 'id' => $f->id]];
            })
            ->all();
    }

    public static function statusBetween(int $viewerId, int $otherId): array
    {
        return static::statusMapFor($viewerId)[$otherId] ?? ['status' => 'none', 'id' => null];
    }
}
