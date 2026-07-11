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
}
