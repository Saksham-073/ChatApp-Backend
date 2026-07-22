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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function heartbeat(Request $request, Call $call)
    {
        abort_unless($call->isParticipant($request->user()->id), 403);

        if ($call->status === 'ongoing') {
            $call->update(['last_seen_at' => now()]);
        }

        return response()->noContent();
    }

    public function seen(Request $request, Call $call)
    {
        abort_unless($call->callee_id === $request->user()->id, 403);

        if ($call->status === 'missed' && $call->seen_at === null) {
            $call->update(['seen_at' => now()]);
        }

        return response()->noContent();
    }

    public function missed(Request $request)
    {
        $calls = Call::with(['caller', 'callee'])
            ->where('callee_id', $request->user()->id)
            ->where('status', 'missed')
            ->whereNull('seen_at')
            ->orderByDesc('id')
            ->get();

        return CallResource::collection($calls);
    }

    public function history(Request $request, Conversation $conversation)
    {
        Gate::authorize('participate', $conversation);

        $page = Call::with(['caller', 'callee'])
            ->where('conversation_id', $conversation->id)
            ->whereNotIn('status', ['ringing', 'ongoing'])
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return CallResource::collection($page);
    }

    public function iceServers()
    {
        $keyId = config('services.cloudflare_turn.key_id');
        $apiToken = config('services.cloudflare_turn.api_token');

        if ($keyId && $apiToken) {
            $servers = $this->fetchCloudflareIceServers($keyId, $apiToken);

            if ($servers !== null) {
                return response()->json(['iceServers' => $servers]);
            }
        }

        $servers = [['urls' => 'stun:stun.l.google.com:19302']];

        if (config('services.turn.url')) {
            $servers[] = [
                'urls' => config('services.turn.url'),
                'username' => config('services.turn.username'),
                'credential' => config('services.turn.credential'),
            ];
        }

        return response()->json(['iceServers' => $servers]);
    }

    /**
     * Fetch short-lived TURN credentials from Cloudflare Realtime.
     * Returns null on any failure so the caller can fall back to
     * STUN-only (logged, so a bad token doesn't fail silently as a 200).
     */
    private function fetchCloudflareIceServers(string $keyId, string $apiToken): ?array
    {
        try {
            $response = Http::withToken($apiToken)
                ->timeout(5)
                ->post("https://rtc.live.cloudflare.com/v1/turn/keys/{$keyId}/credentials/generate-ice-servers", [
                    'ttl' => 86400,
                ]);

            $servers = $response->json('iceServers');

            if ($response->successful() && is_array($servers)) {
                return $servers;
            }

            Log::warning('Cloudflare TURN credential fetch failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Cloudflare TURN credential fetch threw', ['message' => $e->getMessage()]);
        }

        return null;
    }
}
