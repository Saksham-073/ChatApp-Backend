<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
}
