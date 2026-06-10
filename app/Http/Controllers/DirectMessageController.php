<?php

namespace App\Http\Controllers;

use App\Events\DirectMessageSent;
use App\Http\Resources\DirectMessageResource;
use App\Models\Conversation;
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
}
