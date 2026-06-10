<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DirectMessageController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public auth routes (throttled to slow brute-force attempts)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Group chat
    Route::get('/chat/rooms', [ChatController::class, 'rooms']);
    Route::post('/chat/rooms', [ChatController::class, 'createRoom']);
    Route::get('/chat/room/{roomId}/messages', [ChatController::class, 'messages']);
    Route::post('/chat/room/{roomId}/messages', [ChatController::class, 'newMessage'])
        ->middleware('throttle:60,1');
    Route::patch('/chat/room/{roomId}/messages/{message}', [ChatController::class, 'updateMessage'])
        ->middleware('throttle:60,1');

    // Direct messages
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{conversation}/messages', [DirectMessageController::class, 'index']);
    Route::post('/conversations/{conversation}/messages', [DirectMessageController::class, 'store'])
        ->middleware('throttle:60,1');
    Route::post('/conversations/{conversation}/read', [DirectMessageController::class, 'markRead']);
});
