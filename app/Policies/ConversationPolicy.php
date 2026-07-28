<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Only the two participants of a conversation may read messages in it.
     */
    public function participate(User $user, Conversation $conversation): bool
    {
        return $conversation->user_one_id === $user->id
            || $conversation->user_two_id === $user->id;
    }

    /**
     * Sending requires participation AND friendship. This is the actual
     * enforcement point for "must be friends to message".
     */
    public function message(User $user, Conversation $conversation): bool
    {
        if (! $this->participate($user, $conversation)) {
            return false;
        }

        $otherId = $conversation->user_one_id === $user->id
            ? $conversation->user_two_id
            : $conversation->user_one_id;

        return Friendship::statusBetween($user->id, $otherId)['status'] === 'friends';
    }
}
