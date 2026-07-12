<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $conversations = Conversation::where(function ($q) use ($userId) {
            $q->where('user_one_id', $userId)->orWhere('user_two_id', $userId);
        })
            ->with(['userOne:id,name,email', 'userTwo:id,name,email', 'latestMessage'])
            ->withCount(['messages as unread_count' => function ($q) use ($userId) {
                $q->whereNull('read_at')->where('sender_id', '!=', $userId);
            }])
            // most recently active conversation first
            ->orderByDesc(
                DirectMessage::select('created_at')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->latest()
                    ->limit(1)
            )
            ->get();

        return ConversationResource::collection($conversations);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => [
                'required', 'integer', 'exists:users,id',
                Rule::notIn([$request->user()->id]), // cannot start a conversation with yourself
            ],
        ]);

        $userId = $request->user()->id;
        $otherId = (int) $request->user_id;

        // Always store with lower id first to guarantee uniqueness
        [$a, $b] = $userId < $otherId ? [$userId, $otherId] : [$otherId, $userId];

        $conv = Conversation::where(['user_one_id' => $a, 'user_two_id' => $b])->first();

        if (! $conv) {
            abort_unless(
                Friendship::statusBetween($userId, $otherId)['status'] === 'friends',
                403,
                'You must be friends to start a conversation.'
            );

            try {
                $conv = Conversation::create(['user_one_id' => $a, 'user_two_id' => $b]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Lost a race to create the same pair — the winner's row now exists.
                $conv = Conversation::where(['user_one_id' => $a, 'user_two_id' => $b])->first();
                if (! $conv) {
                    throw $e;
                }
            }
        }

        $conv->load(['userOne:id,name,email', 'userTwo:id,name,email', 'latestMessage']);
        $conv->loadCount(['messages as unread_count' => function ($q) use ($userId) {
            $q->whereNull('read_at')->where('sender_id', '!=', $userId);
        }]);

        return (new ConversationResource($conv))
            ->response()
            ->setStatusCode(201);
    }
}
