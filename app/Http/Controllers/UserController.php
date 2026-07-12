<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $viewerId = $request->user()->id;
        $users = User::where('id', '!=', $viewerId)->orderBy('name')->get();
        $statusMap = Friendship::statusMapFor($viewerId);

        return $users->map(function (User $u) use ($statusMap) {
            $rel = $statusMap[$u->id] ?? ['status' => 'none', 'id' => null];

            return [
                ...(new UserResource($u))->resolve(),
                'friendship_status' => $rel['status'],
                'friendship_id' => $rel['id'],
            ];
        });
    }
}
