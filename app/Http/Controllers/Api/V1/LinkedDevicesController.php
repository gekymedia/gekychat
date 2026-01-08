<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class LinkedDevicesController extends Controller
{
    /**
     * Get all linked devices (tokens) for the authenticated user.
     * GET /linked-devices
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $currentToken = $request->user()->currentAccessToken();

        $tokens = $user->tokens()
            ->orderBy('last_used_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $currentTokenId = $currentToken ? $currentToken->id : null;

        // Also get web sessions from UserSession model
        $webSessions = \App\Models\UserSession::where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->get();

        $devices = $tokens->map(function ($token) use ($currentTokenId) {
            return [
                'id' => $token->id,
                'name' => $token->name ?? 'Unknown Device',
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
                'is_current_device' => $token->id === $currentTokenId,
                'device_type' => 'mobile_desktop', // Mobile or Desktop app
            ];
        });

        // Add web sessions to the list
        $currentSessionId = session()->getId();
        foreach ($webSessions as $session) {
            $devices->push([
                'id' => 'web_' . $session->session_id,
                'name' => $session->device_type . ' - ' . $session->browser . ' (' . $session->platform . ')',
                'last_used_at' => $session->last_activity?->toIso8601String(),
                'created_at' => $session->created_at->toIso8601String(),
                'is_current_device' => $session->session_id === $currentSessionId,
                'device_type' => 'web',
                'session_id' => $session->session_id,
            ]);
        }

        return response()->json([
            'data' => $devices->values(),
            'current_token_id' => $currentTokenId,
        ]);
    }

    /**
     * Delete a linked device (token or web session).
     * DELETE /linked-devices/{id}
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        // Check if it's a web session ID (starts with 'web_')
        if (str_starts_with($id, 'web_')) {
            $sessionId = str_replace('web_', '', $id);
            $session = \App\Models\UserSession::where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->first();

            if (!$session) {
                return response()->json([
                    'error' => 'Device not found',
                ], 404);
            }

            // Delete from user_sessions table
            $session->delete();

            // Also delete from sessions table if using database sessions
            if (config('session.driver') === 'database') {
                \Illuminate\Support\Facades\DB::table('sessions')
                    ->where('id', $sessionId)
                    ->delete();
            }

            return response()->json([
                'message' => 'Device unlinked successfully',
            ]);
        }

        // Otherwise, it's a token ID
        $token = $user->tokens()->find($id);

        if (!$token) {
            return response()->json([
                'error' => 'Device not found',
            ], 404);
        }

        $token->delete();

        return response()->json([
            'message' => 'Device unlinked successfully',
        ]);
    }

    /**
     * Delete all other devices (keep current one).
     * DELETE /linked-devices/others
     */
    public function destroyOthers(Request $request)
    {
        $user = $request->user();
        $currentToken = $request->user()->currentAccessToken();

        if (!$currentToken) {
            return response()->json([
                'error' => 'No current token found',
            ], 400);
        }

        // Delete all other tokens
        $deletedTokenCount = $user->tokens()
            ->where('id', '!=', $currentToken->id)
            ->delete();

        // Delete all other web sessions
        $currentSessionId = session()->getId();
        $deletedSessionCount = \App\Models\UserSession::where('user_id', $user->id)
            ->where('session_id', '!=', $currentSessionId)
            ->delete();

        // Also delete from sessions table if using database sessions
        if (config('session.driver') === 'database' && $currentSessionId) {
            \Illuminate\Support\Facades\DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();
        }

        $totalDeleted = $deletedTokenCount + $deletedSessionCount;

        // Return success even if no devices were deleted (already logged out from other devices)
        return response()->json([
            'message' => $totalDeleted > 0 
                ? "$totalDeleted device(s) unlinked successfully"
                : 'No other devices to unlink',
            'deleted_count' => $totalDeleted,
        ]);
    }
}

