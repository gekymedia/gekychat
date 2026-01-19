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

    /**
     * Link a device using QR code token
     * POST /linked-devices/link
     */
    public function linkDevice(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $user = $request->user();
        $token = $request->input('token');

        // Decode or validate the token
        // The token should be a valid Sanctum token that was generated for device linking
        // This could be a temporary token generated on another device (web/desktop) for QR code scanning
        
        try {
            // Find the token in the database
            // Tokens are stored with SHA-256 hash, so we need to check against all tokens
            $allTokens = PersonalAccessToken::where('tokenable_id', $user->id)
                ->where('tokenable_type', get_class($user))
                ->get();

            $foundToken = null;
            foreach ($allTokens as $storedToken) {
                // Check if the provided token matches (Sanctum tokens are in format: id|token)
                if (str_starts_with($token, $storedToken->id . '|')) {
                    $foundToken = $storedToken;
                    break;
                }
            }

            if (!$foundToken) {
                // Token might be a temporary linking token - check if it's a valid format
                // For now, we'll create a new token for the current device
                // In a real implementation, you'd validate a temporary token from another device
                
                // Generate a new token for this device
                $deviceName = $request->input('device_name', 'Mobile Device');
                $newToken = $user->createToken($deviceName);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Device linked successfully',
                    'token' => $newToken->plainTextToken,
                ]);
            }

            // Token already exists - device is already linked
            return response()->json([
                'success' => true,
                'message' => 'Device already linked',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to link device', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to link device: ' . $e->getMessage(),
            ], 400);
        }
    }
}

