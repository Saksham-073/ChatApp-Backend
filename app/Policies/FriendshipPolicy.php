<?php

namespace App\Policies;

use App\Models\Friendship;
use App\Models\User;

class FriendshipPolicy
{
    /**
     * Only the recipient of a still-pending request may accept it.
     */
    public function accept(User $user, Friendship $friendship): bool
    {
        return $friendship->status === 'pending' && $friendship->recipient_id === $user->id;
    }

    /**
     * Either party of a still-pending request may cancel/decline it.
     */
    public function cancel(User $user, Friendship $friendship): bool
    {
        return $friendship->sender_id === $user->id || $friendship->recipient_id === $user->id;
    }
}
