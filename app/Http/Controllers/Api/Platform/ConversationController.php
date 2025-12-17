<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /**
     * Find or create a conversation between a system bot and a user
     */
    public function findOrCreate(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'bot_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $userId = $request->user_id;
        $botUserId = $request->bot_user_id ?? config('services.gekychat.system_bot_user_id', 0);

        // If bot_user_id is 0 or not provided, use the system bot (phone: 0000000000)
        if ($botUserId === 0) {
            $botUser = User::where('phone', '0000000000')->first();
            if ($botUser) {
                $botUserId = $botUser->id;
            } else {
                return response()->json([
                    'error' => 'System bot user not found',
                ], 404);
            }
        }

        // Find or create conversation
        $conversation = Conversation::findOrCreateDirect($botUserId, $userId, $botUserId);

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'bot_user_id' => $botUserId,
            ]
        ]);
    }
}
