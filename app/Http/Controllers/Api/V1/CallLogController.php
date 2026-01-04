<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use Illuminate\Http\Request;

class CallLogController extends Controller
{
    /**
     * GET /api/v1/calls
     * Get call logs for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get all calls where user is either caller or callee
        $calls = CallSession::where(function($query) use ($user) {
                $query->where('caller_id', $user->id)
                      ->orWhere('callee_id', $user->id);
            })
            ->whereNull('group_id') // Only direct calls for now
            ->with(['caller:id,name,phone,avatar_path', 'callee:id,name,phone,avatar_path'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        // Transform calls
        $calls->getCollection()->transform(function($call) use ($user) {
            $duration = null;
            if ($call->started_at && $call->ended_at) {
                $duration = $call->started_at->diffInSeconds($call->ended_at);
            }
            
            $isMissed = !$call->started_at || ($duration !== null && $duration < 2);
            $isOutgoing = $call->caller_id === $user->id;
            $otherUser = $isOutgoing ? $call->callee : $call->caller;
            
            return [
                'id' => $call->id,
                'type' => $call->type,
                'duration' => $duration,
                'is_missed' => $isMissed,
                'is_outgoing' => $isOutgoing,
                'other_user' => $otherUser ? [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'phone' => $otherUser->phone,
                    'avatar_url' => $otherUser->avatar_path ? asset('storage/'.$otherUser->avatar_path) : null,
                ] : null,
                'started_at' => $call->started_at?->toIso8601String(),
                'ended_at' => $call->ended_at?->toIso8601String(),
                'created_at' => $call->created_at->toIso8601String(),
            ];
        });
        
        return response()->json([
            'data' => $calls->items(),
            'pagination' => [
                'current_page' => $calls->currentPage(),
                'last_page' => $calls->lastPage(),
                'per_page' => $calls->perPage(),
                'total' => $calls->total(),
            ],
        ]);
    }
}


