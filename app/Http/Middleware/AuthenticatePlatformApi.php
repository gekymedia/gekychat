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
        // Try api-client guard first (platform API clients)
        if (Auth::guard('api-client')->check()) {
            $client = Auth::guard('api-client')->user();
            $request->attributes->set('api_client', $client);
            return $next($request);
        }

        // Try sanctum guard (user API keys - legacy Sanctum tokens)
        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            $request->attributes->set('api_client', null);
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
