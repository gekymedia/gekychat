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

        // Try sanctum guard (user API keys)
        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            // For user API keys, we don't have an api_client, but we can check the user's privilege
            $request->attributes->set('api_client', null);
            $request->setUserResolver(function () use ($user) {
                return $user;
            });
            return $next($request);
        }

        // Neither guard authenticated
        return response()->json([
            'error' => 'Unauthorized',
            'message' => 'Valid API credentials required'
        ], 401);
    }
}
