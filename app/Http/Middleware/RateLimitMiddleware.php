<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Responses\ErrorResponse;

/**
 * ✅ MODERN: Rate limiting middleware for API protection
 * Prevents abuse and DDoS attacks
 */
class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1)
    {
        $key = $this->resolveRequestSignature($request);
        $attempts = (int)Redis::get($key) ?: 0;
        
        if ($attempts >= $maxAttempts) {
            return ErrorResponse::rateLimit($decayMinutes * 60);
        }
        
        // Increment attempts
        if ($attempts === 0) {
            Redis::setex($key, $decayMinutes * 60, 1);
        } else {
            Redis::incr($key);
        }
        
        $response = $next($request);
        
        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $maxAttempts - $attempts - 1));
        
        return $response;
    }
    
    /**
     * Resolve request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();
        $userId = $user ? $user->id : 'guest';
        $route = $request->route()->getName() ?: $request->path();
        
        return "rate_limit:{$userId}:{$route}";
    }
}
