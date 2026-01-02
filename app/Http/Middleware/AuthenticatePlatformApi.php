<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Authenticate Platform API requests
 * Supports both platform API clients (api-client guard) and user API keys (sanctum guard)
 */
class AuthenticatePlatformApi
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Try sanctum guard first (platform API clients and user API keys use Sanctum tokens)
        // Platform API clients get tokens from OAuth endpoint, user API keys also use Sanctum
        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            
            // Check if this is a platform API client token
            $token = $user->currentAccessToken();
            if ($token && $token->name === 'platform') {
                // This is a platform API client token
                // Find the ApiClient by matching the user_id
                $client = \App\Models\ApiClient::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->first();
                $request->attributes->set('api_client', $client);
            } else {
                // This is a user API key token
                $request->attributes->set('api_client', null);
            }
            
            $request->setUserResolver(function () use ($user) {
                return $user;
            });
            return $next($request);
        }

        // Try user API keys (new client_id/client_secret format)
        $token = $request->bearerToken();
        if ($token) {
            // Check if it's a user API key (starts with sk_)
            if (str_starts_with($token, 'sk_')) {
                $userApiKey = \App\Models\UserApiKey::where('is_active', true)
                    ->get()
                    ->first(function ($key) use ($token) {
                        return $key->verifySecret($token);
                    });
                
                if ($userApiKey) {
                    $user = $userApiKey->user;
                    if ($user && $user->developer_mode && $user->developer_client_id) {
                        // Record usage
                        $userApiKey->recordUsage($request->ip());
                        
                        $request->attributes->set('api_client', null);
                        $request->attributes->set('user_api_key', $userApiKey);
                        $request->setUserResolver(function () use ($user) {
                            return $user;
                        });
                        return $next($request);
                    }
                }
            }
        }

        // Neither guard authenticated
        return response()->json([
            'error' => 'Unauthorized',
            'message' => 'Valid API credentials required'
        ], 401);
    }
}
