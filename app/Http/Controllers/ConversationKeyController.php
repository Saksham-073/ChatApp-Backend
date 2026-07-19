<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ConversationKeyController extends Controller
{
    public function store(Request $request, Conversation $conversation)
    {
        Gate::authorize('participate', $conversation);

        $request->validate([
            'keys' => 'required|array|min:1|max:2',
            'keys.*.user_id' => 'required|integer',
            'keys.*.wrapped_key' => 'required|string|max:512',
        ]);

        $participantIds = collect([$conversation->user_one_id, $conversation->user_two_id]);
        $submitted = collect($request->keys);
        $submittedIds = $submitted->pluck('user_id')->map(fn ($id) => (int) $id);

        abort_unless(
            $submittedIds->diff($participantIds)->isEmpty() && $submittedIds->unique()->count() === $submittedIds->count(),
            422,
            'Keys may only target the two conversation participants.'
        );

        $existingIds = ConversationKey::where('conversation_id', $conversation->id)
            ->where('key_version', 1)
            ->pluck('user_id');

        // Initial creation must cover both participants so neither side is locked out
        abort_if(
            $existingIds->isEmpty() && $submittedIds->sort()->values()->all() !== $participantIds->sort()->values()->all(),
            422,
            'Initial key creation must include wraps for both participants.'
        );

        $toInsert = $submitted->filter(fn ($k) => ! $existingIds->contains((int) $k['user_id']));

        // Nothing new to insert — caller lost a creation race or repeated a re-wrap
        abort_if($toInsert->isEmpty(), 409, 'Conversation key already exists.');

        try {
            // Transaction so a mid-loop unique-index collision (concurrent creation
            // submitting rows in a different order) rolls back ALL of this request's
            // inserts — a partial insert would leave the two participants holding
            // wraps of two different conversation keys.
            $created = DB::transaction(fn () => $toInsert->map(fn ($k) => ConversationKey::create([
                'conversation_id' => $conversation->id,
                'user_id' => (int) $k['user_id'],
                'key_version' => 1,
                'wrapped_key' => $k['wrapped_key'],
            ])));
        } catch (\Illuminate\Database\QueryException) {
            // Unique-index race: another request inserted between our check and write
            abort(409, 'Conversation key already exists.');
        }

        return response()->json(
            $created->map->only(['conversation_id', 'user_id', 'key_version', 'wrapped_key'])->values(),
            201
        );
    }

    public function mine(Request $request)
    {
        return ConversationKey::where('user_id', $request->user()->id)
            ->get(['conversation_id', 'key_version', 'wrapped_key']);
    }
}
