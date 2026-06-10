<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conv = Conversation::find($conversationId);

    return $conv && ($conv->user_one_id === $user->id || $conv->user_two_id === $user->id);
});

// Personal notification channel — only the user themselves may listen
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
