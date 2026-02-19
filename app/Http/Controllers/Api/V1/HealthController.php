<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ✅ MODERN: Health check endpoint for monitoring and status checks
 * WhatsApp/Telegram-style service health monitoring
 */
class HealthController extends Controller
{
    /**
     * GET /api/v1/health
     * Returns overall system health status
     */
    public function index(Request $request)
    {
        $startTime = microtime(true);
        
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'services' => [],
            'metrics' => [],
        ];
        
        // Check database connectivity
        try {
            $dbStart = microtime(true);
            DB::connection()->getPdo();
            $dbTime = round((microtime(true) - $dbStart) * 1000, 2);
            
            $health['services']['database'] = [
                'status' => 'healthy',
                'response_time_ms' => $dbTime,
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['services']['database'] = [
                'status' => 'unhealthy',
                'error' => 'Database connection failed',
            ];
            Log::error('Health check: Database connection failed', ['error' => $e->getMessage()]);
        }
        
        // Check cache (Redis) connectivity
        try {
            $cacheStart = microtime(true);
            Cache::put('health_check_test', true, 1);
            $cacheGet = Cache::get('health_check_test');
            $cacheTime = round((microtime(true) - $cacheStart) * 1000, 2);
            
            $health['services']['cache'] = [
                'status' => $cacheGet ? 'healthy' : 'degraded',
                'response_time_ms' => $cacheTime,
            ];
        } catch (\Exception $e) {
            $health['services']['cache'] = [
                'status' => 'degraded',
                'error' => 'Cache unavailable (non-critical)',
            ];
            Log::warning('Health check: Cache unavailable', ['error' => $e->getMessage()]);
        }
        
        // Check Pusher connectivity (broadcasting)
        try {
            $pusherStatus = config('broadcasting.default') === 'pusher' ? 'configured' : 'not_configured';
            $health['services']['pusher'] = [
                'status' => $pusherStatus,
                'driver' => config('broadcasting.default'),
            ];
        } catch (\Exception $e) {
            $health['services']['pusher'] = [
                'status' => 'unknown',
                'error' => 'Configuration check failed',
            ];
        }
        
        // Add database performance metrics
        try {
            $health['metrics']['database'] = [
                'total_messages' => \App\Models\Message::count(),
                'total_conversations' => \App\Models\Conversation::count(),
                'total_users' => \App\Models\User::count(),
            ];
        } catch (\Exception $e) {
            Log::warning('Health check: Metrics collection failed', ['error' => $e->getMessage()]);
        }
        
        // Add response time
        $health['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        
        // Determine HTTP status code based on health
        $httpStatus = $health['status'] === 'healthy' ? 200 : 503;
        
        return response()->json($health, $httpStatus);
    }
    
    /**
     * GET /api/v1/health/detailed
     * Returns detailed health check with per-service metrics
     * (Protected endpoint - requires authentication)
     */
    public function detailed(Request $request)
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'version' => config('app.version', '1.0.0'),
            'services' => [],
            'database' => [],
            'queue' => [],
        ];
        
        // Detailed database checks
        try {
            $health['database'] = [
                'connection' => 'healthy',
                'driver' => config('database.default'),
                'tables' => [
                    'messages' => \App\Models\Message::count(),
                    'conversations' => \App\Models\Conversation::count(),
                    'users' => \App\Models\User::count(),
                    'message_statuses' => DB::table('message_statuses')->count(),
                ],
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['database'] = [
                'connection' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
        
        // Queue health
        try {
            $health['queue'] = [
                'driver' => config('queue.default'),
                'status' => 'configured',
            ];
        } catch (\Exception $e) {
            $health['queue'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
        
        return response()->json($health);
    }
}
