<?php

namespace App\Http\Controllers;

use App\Events\DirectMessageDeleted;
use App\Events\DirectMessageSent;
use App\Events\DirectMessageUpdated;
use App\Http\Resources\DirectMessageResource;
use App\Models\Conversation;
use App\Models\DirectMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DirectMessageController extends Controller
{
    public function index(Request $request, Conversation $conversation)
    {
        Gate::authorize('participate', $conversation);

        $page = $conversation->messages()
            ->with('sender')
            ->orderBy('id', 'DESC')
            ->cursorPaginate(50);

        return DirectMessageResource::collection($page);
    }

    public function store(Request $request, Conversation $conversation)
    {
        Gate::authorize('participate', $conversation);

        $request->validate(['message' => 'required|string|max:2000']);

        $dm = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'message' => $request->message,
        ]);

        $dm->load('sender');

        broadcast(new DirectMessageSent($dm))->toOthers();

        return (new DirectMessageResource($dm))
            ->response()
            ->setStatusCode(201);
    }

    public function markRead(Request $request, Conversation $conversation)
    {
        Gate::authorize('participate', $conversation);

        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $request->user()->id)
            ->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function update(Request $request, Conversation $conversation, DirectMessage $message)
    {
        Gate::authorize('participate', $conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->sender_id === $request->user()->id, 403);
        abort_if($message->deleted_at !== null, 409, 'Message was deleted.');
        abort_if(
            $message->created_at->lt(now()->subMinutes(DirectMessage::EDIT_WINDOW_MINUTES)),
            403,
            'Edit window expired.'
        );

        $request->validate(['message' => 'required|string|max:2000']);

        $message->update(['message' => $request->message, 'edited_at' => now()]);
        $message->load('sender');

        broadcast(new DirectMessageUpdated($message))->toOthers();

        return new DirectMessageResource($message);
    }

    public function destroy(Request $request, Conversation $conversation, DirectMessage $message)
    {
        Gate::authorize('participate', $conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->sender_id === $request->user()->id, 403);

        if ($message->deleted_at === null) {
            $message->update(['message' => '', 'deleted_at' => now()]);
            broadcast(new DirectMessageDeleted($message))->toOthers();
        }

        return response()->noContent();
    }
}
