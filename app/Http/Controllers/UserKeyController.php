<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Events\UserKeysChanged;
use App\Models\Conversation;
use App\Models\ConversationKey;

class UserKeyController extends Controller
{
    private function keysPayload($user): array
    {
        return [
            'public_key' => $user->public_key,
            'encrypted_private_key' => $user->encrypted_private_key,
            'key_salt' => $user->key_salt,
            'key_nonce' => $user->key_nonce,
        ];
    }

    public function show(Request $request)
    {
        $user = $request->user();
        abort_if($user->public_key === null, 404, 'Not enrolled.');

        return response()->json($this->keysPayload($user));
    }

    public function store(Request $request)
    {
        $request->validate([
            'public_key' => 'required|string|max:255',
            'encrypted_private_key' => 'required|string|max:1024',
            'key_salt' => 'required|string|max:64',
            'key_nonce' => 'required|string|max:64',
        ]);

        $user = $request->user();
        abort_if($user->public_key !== null, 409, 'Already enrolled.');

        $user->update($request->only(['public_key', 'encrypted_private_key', 'key_salt', 'key_nonce']));

        return response()->json($this->keysPayload($user), 201);
    }

    public function update(Request $request)
    {
        $request->validate([
            'public_key' => 'prohibited',
            'encrypted_private_key' => 'required|string|max:1024',
            'key_salt' => 'required|string|max:64',
            'key_nonce' => 'required|string|max:64',
        ]);

        $user = $request->user();
        abort_if($user->public_key === null, 404, 'Not enrolled.');

        $user->update($request->only(['encrypted_private_key', 'key_salt', 'key_nonce']));

        return response()->json($this->keysPayload($user));
    }

    public function reset(Request $request)
    {
        $request->validate([
            'public_key' => 'required|string|max:255',
            'encrypted_private_key' => 'required|string|max:1024',
            'key_salt' => 'required|string|max:64',
            'key_nonce' => 'required|string|max:64',
        ]);

        $user = $request->user();
        abort_if($user->public_key === null, 404, 'Not enrolled.');

        $peerIds = Conversation::where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->get()
            ->map(fn ($c) => $c->user_one_id === $user->id ? $c->user_two_id : $c->user_one_id)
            ->unique()
            ->values();

        // Atomic: a failure between the wrap deletion and the escrow swap would
        // strand the user with old wraps sealed to a key that no longer exists
        DB::transaction(function () use ($user, $request) {
            ConversationKey::where('user_id', $user->id)->delete();
            $user->update($request->only(['public_key', 'encrypted_private_key', 'key_salt', 'key_nonce']));
        });

        if ($peerIds->isNotEmpty()) {
            broadcast(new UserKeysChanged($user->id, $user->public_key, $peerIds->all()))->toOthers();
        }

        return response()->json($this->keysPayload($user));
    }
}
