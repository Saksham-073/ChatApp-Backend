<?php

namespace App\Http\Controllers;

use App\Events\CallAccepted;
use App\Events\CallDeclined;
use App\Events\CallEnded;
use App\Events\CallInitiated;
use App\Events\CallMissed;
use App\Http\Resources\CallResource;
use App\Models\Call;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CallController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
            'type' => 'required|in:audio,video',
        ]);

        $conversation = Conversation::findOrFail($request->conversation_id);
        Gate::authorize('participate', $conversation);

        $caller = $request->user();
        $calleeId = $conversation->user_one_id === $caller->id
            ? $conversation->user_two_id
            : $conversation->user_one_id;

        $busy = Call::active()
            ->where(function ($q) use ($caller, $calleeId) {
                $q->whereIn('caller_id', [$caller->id, $calleeId])
                    ->orWhereIn('callee_id', [$caller->id, $calleeId]);
            })->exists();
        abort_if($busy, 409, 'busy');

        $call = Call::create([
            'conversation_id' => $conversation->id,
            'caller_id' => $caller->id,
            'callee_id' => $calleeId,
            'type' => $request->type,
            'status' => 'ringing',
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);
        $call->load(['caller', 'callee']);

        broadcast(new CallInitiated($call));

        return (new CallResource($call))->response()->setStatusCode(201);
    }

    public function accept(Request $request, Call $call)
    {
        abort_unless($call->callee_id === $request->user()->id, 403);
        abort_unless($call->status === 'ringing', 409, 'Call is no longer ringing.');

        $call->update(['status' => 'ongoing', 'answered_at' => now(), 'last_seen_at' => now()]);
        broadcast(new CallAccepted($call))->toOthers();

        return new CallResource($call->load(['caller', 'callee']));
    }

    public function decline(Request $request, Call $call)
    {
        abort_unless($call->callee_id === $request->user()->id, 403);
        abort_unless($call->status === 'ringing', 409, 'Call is no longer ringing.');

        $call->update(['status' => 'declined', 'ended_at' => now()]);
        broadcast(new CallDeclined($call))->toOthers();

        return new CallResource($call->load(['caller', 'callee']));
    }

    public function end(Request $request, Call $call)
    {
        abort_unless($call->isParticipant($request->user()->id), 403);
        $request->validate(['reason' => 'sometimes|in:timeout,cancel,failed']);

        if (! in_array($call->status, ['ringing', 'ongoing'])) {
            // Idempotent: ending an already-final call is a no-op
            return new CallResource($call->load(['caller', 'callee']));
        }

        $reason = $request->input('reason');

        if ($call->status === 'ringing') {
            $call->update(['status' => 'missed', 'ended_at' => now()]);
            broadcast(new CallMissed($call));
        } else {
            $call->update([
                'status' => $reason === 'failed' ? 'failed' : 'ended',
                'ended_at' => now(),
            ]);
        }

        broadcast(new CallEnded($call))->toOthers();

        return new CallResource($call->load(['caller', 'callee']));
    }
}
