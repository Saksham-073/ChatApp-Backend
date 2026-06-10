<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function rooms(Request $request)
    {
        return ChatRoom::orderBy('name')->get(['id', 'name']);
    }

    public function createRoom(Request $request)
    {
        $request->validate(['name' => 'required|string|max:50|unique:chat_rooms,name']);

        $room = ChatRoom::create(['name' => $request->name]);

        return response()->json($room->only('id', 'name'), 201);
    }

    public function messages(Request $request, $roomId)
    {
        $page = ChatMessage::where('chat_room_id', $roomId)
            ->with('user')
            ->orderBy('id', 'DESC')
            ->cursorPaginate(50);

        return ChatMessageResource::collection($page);
    }

    public function newMessage(Request $request, $roomId)
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $newMessage = ChatMessage::create([
            'user_id' => $request->user()->id,
            'chat_room_id' => $roomId,
            'message' => $request->message,
        ]);

        $newMessage->load('user');

        broadcast(new MessageSent($newMessage))->toOthers();

        return (new ChatMessageResource($newMessage))
            ->response()
            ->setStatusCode(201);
    }

    public function updateMessage(Request $request, $roomId, ChatMessage $message)
    {
        abort_unless((int) $message->chat_room_id === (int) $roomId, 404);
        abort_unless($message->user_id === $request->user()->id, 403);
        abort_if($message->deleted_at !== null, 409, 'Message was deleted.');
        abort_if(
            $message->created_at->lt(now()->subMinutes(ChatMessage::EDIT_WINDOW_MINUTES)),
            403,
            'Edit window expired.'
        );

        $request->validate(['message' => 'required|string|max:2000']);

        $message->update(['message' => $request->message, 'edited_at' => now()]);
        $message->load('user');

        broadcast(new MessageUpdated($message))->toOthers();

        return new ChatMessageResource($message);
    }
}
