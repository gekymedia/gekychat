<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class BroadcastingController extends Controller
{
    /**
     * Authorize a channel subscription for Pusher
     * POST /api/v1/broadcasting/auth
     */
    public function auth(Request $request)
    {
        $request->validate([
            'socket_id' => 'required|string',
            'channel_name' => 'required|string',
        ]);

        // Support both Sanctum (API/mobile) and web session (browser)
        $user = Auth::guard('sanctum')->user() ?? Auth::guard('web')->user();
        if (!$user) {
            Log::warning('Pusher auth: User not authenticated', [
                'channel_name' => $request->channel_name,
                'socket_id' => $request->socket_id,
            ]);
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }
        Auth::setUser($user);
        
        Log::info('=== PUSHER AUTH REQUEST (API) ===', [
            'channel_name' => $request->channel_name,
            'socket_id' => $request->socket_id,
            'user_id' => $user->id,
        ]);

        try {
            $response = Broadcast::auth($request);
            
            Log::info('=== PUSHER AUTH SUCCESS (API) ===', [
                'channel_name' => $request->channel_name,
                'user_id' => $user->id,
            ]);
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Pusher auth error', [
                'channel_name' => $request->channel_name,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Unauthorized: ' . $e->getMessage(),
            ], 403);
        }
    }
}

