<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Only the two participants of a conversation may read or send messages in it.
     */
    public function participate(User $user, Conversation $conversation): bool
    {
        return $conversation->user_one_id === $user->id
            || $conversation->user_two_id === $user->id;
    }
}
