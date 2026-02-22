<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows broadcast auth to work for both:
 * - Web (session cookie)
 * - API/Mobile (Bearer token via Sanctum)
 */
class EnsureBroadcastAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Try Sanctum first (Bearer token from API/mobile)
        if (Auth::guard('sanctum')->check()) {
            Auth::setUser(Auth::guard('sanctum')->user());
            return $next($request);
        }
        // Fallback to web session (browser with session cookie)
        if (Auth::guard('web')->check()) {
            Auth::setUser(Auth::guard('web')->user());
            return $next($request);
        }
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
}
