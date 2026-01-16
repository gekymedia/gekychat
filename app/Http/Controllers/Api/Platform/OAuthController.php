<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\UserApiKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class OAuthController extends Controller
{
    /**
     * Exchange client credentials for an access token
     * Uses only UserApiKey system (generated from Settings page)
     */
    public function token(Request $request)
    {
        $request->validate([
            'client_id'     => 'required|string',
            'client_secret' => 'required|string',
            'grant_type'    => 'required|in:client_credentials',
        ]);

        // Log the attempt (without logging the secret)
        \Log::info('OAuth token request', [
            'client_id' => $request->client_id,
            'has_client_secret' => !empty($request->client_secret),
            'grant_type' => $request->grant_type,
        ]);

        $user = null;
        $userApiKey = null;

        // Find user by developer_client_id (if column exists)
        // Otherwise, we'll search through all active API keys
        if (str_starts_with($request->client_id, 'dev_')) {
            // Check if developer_client_id column exists
            if (Schema::hasColumn('users', 'developer_client_id')) {
                $user = User::where('developer_client_id', $request->client_id)
                    ->where('developer_mode', true)
                    ->first();
            }

            if ($user) {
                // Find an active user API key for this user and verify secret matches
                $userApiKeys = UserApiKey::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->get();
                
                // Find the API key that matches the provided secret
                foreach ($userApiKeys as $key) {
                    if (Hash::check($request->client_secret, $key->client_secret)) {
                        $userApiKey = $key;
                        break;
                    }
                }
            } else {
                // Fallback: Try to find by matching client_secret across all active API keys
                // This works if developer_client_id column doesn't exist
                $allApiKeys = UserApiKey::where('is_active', true)->get();
                foreach ($allApiKeys as $key) {
                    if (Hash::check($request->client_secret, $key->client_secret)) {
                        $userApiKey = $key;
                        $user = $key->user;
                        break;
                    }
                }
            }
        }

        if (!$userApiKey || !$user) {
            \Log::warning('OAuth: Client not found', [
                'client_id' => $request->client_id,
                'client_id_format' => str_starts_with($request->client_id, 'dev_') ? 'dev_*' : 'other',
            ]);
            return response()->json([
                'error' => 'invalid_client',
                'message' => 'Invalid client credentials',
            ], 401);
        }

        // Secret and is_active already verified during lookup, proceed to create token

        // Record usage
        $userApiKey->recordUsage($request->ip());

        // Create token for the user
        $token = $user->createToken(
            name: 'user-api-key',
            abilities: ['messages.send'] // User API keys have basic permissions
        )->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => null,
            'scope'        => ['messages.send'],
        ]);
    }

    /**
     * Alias for token() method for backward compatibility
     * Some clients may call issueToken instead of token
     */
    public function issueToken(Request $request)
    {
        return $this->token($request);
    }

    /**
     * Revoke current platform token d
     */
    public function revoke(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['revoked' => true]);
    }
}
