<?php

namespace App\Http\Controllers;

use App\Events\FriendRequestAccepted;
use App\Events\FriendRequestCancelled;
use App\Events\FriendRequestSent;
use App\Http\Resources\FriendshipResource;
use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FriendRequestController extends Controller
{
    public function index(Request $request)
    {
        $viewerId = $request->user()->id;

        $incoming = Friendship::where('recipient_id', $viewerId)
            ->where('status', 'pending')
            ->with('sender')
            ->get();

        $outgoing = Friendship::where('sender_id', $viewerId)
            ->where('status', 'pending')
            ->with('recipient')
            ->get();

        return [
            'incoming' => FriendshipResource::collection($incoming),
            'outgoing' => FriendshipResource::collection($outgoing),
        ];
    }

    public function store(Request $request)
    {
        $request->validate([
            'recipient_id' => [
                'required', 'integer', 'exists:users,id',
                Rule::notIn([$request->user()->id]),
            ],
        ]);

        $senderId = $request->user()->id;
        $recipientId = (int) $request->recipient_id;

        $existing = Friendship::where('sender_id', $senderId)
            ->where('recipient_id', $recipientId)
            ->first();
        abort_if($existing?->status === 'accepted', 422, 'Already friends.');
        abort_if($existing?->status === 'pending', 422, 'Request already sent.');

        $reverse = Friendship::where('sender_id', $recipientId)
            ->where('recipient_id', $senderId)
            ->first();

        if ($reverse) {
            abort_if($reverse->status === 'accepted', 422, 'Already friends.');

            $reverse->update(['status' => 'accepted']);
            $reverse->load('sender', 'recipient');
            broadcast(new FriendRequestAccepted($reverse));

            return new FriendshipResource($reverse);
        }

        $friendship = Friendship::create([
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'status' => 'pending',
        ]);
        $friendship->load('sender', 'recipient');

        broadcast(new FriendRequestSent($friendship));

        return (new FriendshipResource($friendship))
            ->response()
            ->setStatusCode(201);
    }

    public function accept(Request $request, Friendship $friendship)
    {
        Gate::authorize('accept', $friendship);

        $friendship->update(['status' => 'accepted']);
        $friendship->load('sender', 'recipient');

        broadcast(new FriendRequestAccepted($friendship));

        return new FriendshipResource($friendship);
    }

    public function destroy(Request $request, Friendship $friendship)
    {
        Gate::authorize('cancel', $friendship);
        abort_if($friendship->status !== 'pending', 409, 'Request already resolved.');

        $viewerId = $request->user()->id;
        $notifyUserId = $friendship->sender_id === $viewerId
            ? $friendship->recipient_id
            : $friendship->sender_id;
        $id = $friendship->id;

        $friendship->delete();

        broadcast(new FriendRequestCancelled($id, $notifyUserId));

        return response()->noContent();
    }
}
