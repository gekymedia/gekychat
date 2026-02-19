<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ✅ MODERN: Cache-Control headers for GET responses
 * Appropriate max-age for API and static-like responses
 */
class CacheControlMiddleware
{
    public function handle(Request $request, Closure $next, ?string $maxAge = null): Response
    {
        $response = $next($request);

        if ($request->method() !== 'GET' || !$response->isSuccessful()) {
            return $response;
        }

        $seconds = $maxAge !== null ? (int) $maxAge : 60;
        $response->headers->set('Cache-Control', "public, max-age={$seconds}, stale-while-revalidate=30");
        return $response;
    }
}
