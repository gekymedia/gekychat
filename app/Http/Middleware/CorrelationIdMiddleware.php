<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * ✅ MODERN: Request tracing with correlation IDs
 * Adds X-Request-ID to response for debugging and distributed tracing
 */
class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Request-ID') ?: Str::uuid()->toString();
        $request->attributes->set('correlation_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $id);
        return $response;
    }
}
