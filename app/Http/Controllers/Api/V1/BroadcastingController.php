<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

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

        // Use Laravel's built-in broadcasting authentication
        try {
            $response = Broadcast::auth($request);
            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
    }
}

