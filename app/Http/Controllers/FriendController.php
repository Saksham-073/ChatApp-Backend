<?php

namespace App\Http\Controllers;

use App\Events\FriendRemoved;
use App\Http\Resources\UserResource;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    public function index(Request $request)
    {
        $viewerId = $request->user()->id;

        $friendships = Friendship::where('status', 'accepted')
            ->where(function ($q) use ($viewerId) {
                $q->where('sender_id', $viewerId)->orWhere('recipient_id', $viewerId);
            })
            ->with(['sender:id,name,email', 'recipient:id,name,email'])
            ->get();

        return UserResource::collection(
            $friendships->map(fn (Friendship $f) => $f->sender_id === $viewerId ? $f->recipient : $f->sender)
        );
    }

    public function destroy(Request $request, User $user)
    {
        $viewerId = $request->user()->id;

        $friendship = Friendship::where('status', 'accepted')
            ->where(function ($q) use ($viewerId, $user) {
                $q->where(['sender_id' => $viewerId, 'recipient_id' => $user->id])
                    ->orWhere(['sender_id' => $user->id, 'recipient_id' => $viewerId]);
            })
            ->firstOrFail();

        $friendship->delete();

        broadcast(new FriendRemoved($viewerId, $user->id));

        return response()->noContent();
    }
}
