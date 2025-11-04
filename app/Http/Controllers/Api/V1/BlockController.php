<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Controller to handle blocking and unblocking users. Blocking prevents the
 * blocked user from sending messages or interacting with the blocking user.
 */
class BlockController extends Controller
{
    /**
     * Block a user. Optionally provide a reason.
     */
    public function block(Request $request, $userId)
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);
        $target = User::findOrFail($userId);
        $actor = $request->user();

        if ($actor->id === $target->id) {
            return response()->json(['error' => 'You cannot block yourself'], 422);
        }

        // Attach if not already blocked
        $actor->blockedUsers()->syncWithoutDetaching([$target->id => ['reason' => $request->reason]]);

        return response()->json(['message' => 'User blocked']);
    }

    /**
     * Unblock a user.
     */
    public function unblock(Request $request, $userId)
    {
        $target = User::findOrFail($userId);
        $actor = $request->user();
        $actor->blockedUsers()->detach($target->id);
        return response()->json(['message' => 'User unblocked']);
    }
}