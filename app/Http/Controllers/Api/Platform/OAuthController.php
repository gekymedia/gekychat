<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

        $client = ApiClient::where('client_id', $request->client_id)
            ->where('is_active', true)
            ->first();

        if (!$client || !Hash::check($request->client_secret, $client->client_secret)) {
            return response()->json([
                'error' => 'invalid_client',
                'message' => 'Invalid client credentials',
            ], 401);
        }

        $token = $client->createToken(
            name: 'platform',
            abilities: $client->scopes ?? ['messages.send']
        )->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => null, // Sanctum tokens do not expire by default
            'scope'        => $client->scopes,
        ]);
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
