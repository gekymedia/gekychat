<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

/**
 * ✅ MODERN: Idempotency middleware for write operations
 * Prevents duplicate requests from creating duplicate data
 * WhatsApp/Telegram-style request deduplication
 */
class IdempotencyMiddleware
{
    private const TTL = 86400; // 24 hours
    
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only apply to write operations
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }
        
        // Get idempotency key from header
        $idempotencyKey = $request->header('X-Idempotency-Key') ??
                          $request->header('Idempotency-Key');
        
        if (!$idempotencyKey) {
            // No idempotency key provided - proceed normally
            return $next($request);
        }
        
        // Check if we've seen this idempotency key before
        $cacheKey = "idempotency:{$idempotencyKey}";
        $cachedResponse = Redis::get($cacheKey);
        
        if ($cachedResponse) {
            // Return cached response
            $data = json_decode($cachedResponse, true);
            return response()->json($data['body'], $data['status'])
                ->withHeaders($data['headers'] ?? []);
        }
        
        // Process request
        $response = $next($request);
        
        // Cache successful responses (2xx status codes)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $responseData = [
                'body' => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ];
            
            Redis::setex($cacheKey, self::TTL, json_encode($responseData));
        }
        
        return $response;
    }
}
