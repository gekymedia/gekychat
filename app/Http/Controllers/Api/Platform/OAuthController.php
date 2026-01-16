<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\UserApiKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    /**
     * Exchange client credentials for an access token
     * Similar to WhatsApp / Meta Cloud API
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

        // Check if it's a platform client (ApiClient) or user API key (UserApiKey)
        $client = null;
        $user = null;
        $userApiKey = null;
        $isUserApiKey = false;

        // Prioritize user API keys if client_id starts with 'dev_'
        // This is the primary authentication method for developer users (UserApiKey system)
        if (str_starts_with($request->client_id, 'dev_')) {
            $user = User::where('developer_client_id', $request->client_id)
                ->where('developer_mode', true)
                ->first();

            if ($user) {
                // Find an active user API key for this user
                $userApiKey = UserApiKey::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->first();

                if ($userApiKey) {
                    $isUserApiKey = true;
                }
            }
        } else {
            // For non-dev_ client_ids, check platform API clients (only if table exists)
            // This is for enterprise/platform API clients (ApiClient system)
            try {
                if (Schema::hasTable('api_clients')) {
                    $client = ApiClient::where('client_id', $request->client_id)->first();
                }
            } catch (\Exception $e) {
                // Table doesn't exist, skip platform client check
                \Log::debug('OAuth: api_clients table not available', ['error' => $e->getMessage()]);
            }
        }

        if (!$client && !$userApiKey) {
            \Log::warning('OAuth: Client not found', [
                'client_id' => $request->client_id,
                'checked_platform' => true,
                'checked_user_api_key' => str_starts_with($request->client_id, 'dev_'),
            ]);
            return response()->json([
                'error' => 'invalid_client',
                'message' => 'Invalid client credentials',
            ], 401);
        }

        // Validate platform client
        if ($client) {
            if (!$client->is_active) {
                \Log::warning('OAuth: Client is inactive', [
                    'client_id' => $request->client_id,
                    'is_active' => $client->is_active,
                ]);
                return response()->json([
                    'error' => 'invalid_client',
                    'message' => 'Client is not active',
                ], 401);
            }

            if (!Hash::check($request->client_secret, $client->client_secret)) {
                \Log::warning('OAuth: Client secret mismatch', [
                    'client_id' => $request->client_id,
                    'client_exists' => true,
                    'client_is_active' => $client->is_active,
                ]);
                return response()->json([
                    'error' => 'invalid_client',
                    'message' => 'Invalid client credentials',
                ], 401);
            }

            // Create token for the user who owns the ApiClient
            // ApiClient doesn't have HasApiTokens, so we create token on the user
            $user = $client->user;
            if (!$user) {
                \Log::error('OAuth: ApiClient has no associated user', [
                    'client_id' => $request->client_id,
                    'api_client_id' => $client->id,
                ]);
                return response()->json([
                    'error' => 'server_error',
                    'message' => 'Client configuration error',
                ], 500);
            }

            $token = $user->createToken(
                name: 'platform',
                abilities: $client->scopes ?? ['messages.send']
            )->plainTextToken;

            return response()->json([
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'expires_in'   => null,
                'scope'        => $client->scopes ?? ['messages.send'],
            ]);
        }

        // Validate user API key
        if ($userApiKey) {
            if (!$userApiKey->is_active) {
                \Log::warning('OAuth: User API key is inactive', [
                    'client_id' => $request->client_id,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'error' => 'invalid_client',
                    'message' => 'API key is not active',
                ], 401);
            }

            if (!Hash::check($request->client_secret, $userApiKey->client_secret)) {
                \Log::warning('OAuth: User API key secret mismatch', [
                    'client_id' => $request->client_id,
                    'user_id' => $user->id,
                    'api_key_id' => $userApiKey->id,
                ]);
                return response()->json([
                    'error' => 'invalid_client',
                    'message' => 'Invalid client credentials',
                ], 401);
            }

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
