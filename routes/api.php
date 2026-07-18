<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationKeyController;
use App\Http\Controllers\DirectMessageController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\FriendRequestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserKeyController;
use Illuminate\Support\Facades\Route;

// Public auth routes (throttled to slow brute-force attempts)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // E2E encryption key escrow
    Route::get('/me/keys', [UserKeyController::class, 'show']);
    Route::post('/me/keys', [UserKeyController::class, 'store']);
    Route::patch('/me/keys', [UserKeyController::class, 'update']);
    Route::get('/me/conversation-keys', [ConversationKeyController::class, 'mine']);

    // Group chat
    Route::get('/chat/rooms', [ChatController::class, 'rooms']);
    Route::post('/chat/rooms', [ChatController::class, 'createRoom']);
    Route::get('/chat/room/{roomId}/messages', [ChatController::class, 'messages']);
    Route::post('/chat/room/{roomId}/messages', [ChatController::class, 'newMessage'])
        ->middleware('throttle:60,1');
    Route::patch('/chat/room/{roomId}/messages/{message}', [ChatController::class, 'updateMessage'])
        ->middleware('throttle:60,1');
    Route::delete('/chat/room/{roomId}/messages/{message}', [ChatController::class, 'destroyMessage']);
    Route::post('/chat/room/{roomId}/typing', [ChatController::class, 'typing'])
        ->middleware('throttle:40,1');

    // Direct messages
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{conversation}/messages', [DirectMessageController::class, 'index']);
    Route::post('/conversations/{conversation}/messages', [DirectMessageController::class, 'store'])
        ->middleware('throttle:60,1');
    Route::patch('/conversations/{conversation}/messages/{message}', [DirectMessageController::class, 'update'])
        ->middleware('throttle:60,1');
    Route::delete('/conversations/{conversation}/messages/{message}', [DirectMessageController::class, 'destroy']);
    Route::post('/conversations/{conversation}/read', [DirectMessageController::class, 'markRead']);
    Route::post('/conversations/{conversation}/typing', [DirectMessageController::class, 'typing'])
        ->middleware('throttle:40,1');
    Route::post('/conversations/{conversation}/keys', [ConversationKeyController::class, 'store']);

    // Friend requests
    Route::get('/friend-requests', [FriendRequestController::class, 'index']);
    Route::post('/friend-requests', [FriendRequestController::class, 'store']);
    Route::post('/friend-requests/{friendship}/accept', [FriendRequestController::class, 'accept']);
    Route::delete('/friend-requests/{friendship}', [FriendRequestController::class, 'destroy']);

    // Friends
    Route::get('/friends', [FriendController::class, 'index']);
    Route::delete('/friends/{user}', [FriendController::class, 'destroy']);
});
