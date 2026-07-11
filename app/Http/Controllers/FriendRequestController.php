<?php

namespace App\Http\Controllers;

use App\Events\FriendRequestSent;
use App\Http\Resources\FriendshipResource;
use App\Models\Friendship;
use Illuminate\Http\Request;
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
}
