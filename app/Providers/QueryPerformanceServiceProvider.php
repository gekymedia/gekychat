<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryPerformanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only enable query logging in non-production or when explicitly enabled
        if (!app()->environment('production') || config('database.log_slow_queries', false)) {
            $this->enableSlowQueryLogging();
        }
        
        // Enable query counting for debugging
        if (config('database.log_query_count', false)) {
            $this->enableQueryCounting();
        }
    }
    
    /**
     * Enable slow query logging
     */
    private function enableSlowQueryLogging(): void
    {
        $threshold = config('database.slow_query_threshold', 1000); // milliseconds
        
        DB::listen(function ($query) use ($threshold) {
            if ($query->time > $threshold) {
                Log::channel('slow_queries')->warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms',
                    'connection' => $query->connectionName,
                    'url' => request()->fullUrl(),
                    'user_id' => auth()->id(),
                ]);
            }
        });
    }
    
    /**
     * Enable query counting for debugging
     */
    private function enableQueryCounting(): void
    {
        $queryCount = 0;
        
        DB::listen(function ($query) use (&$queryCount) {
            $queryCount++;
        });
        
        // Log query count at the end of request
        app()->terminating(function () use (&$queryCount) {
            if ($queryCount > 50) { // Warn if more than 50 queries
                Log::channel('performance')->warning('High query count detected', [
                    'count' => $queryCount,
                    'url' => request()->fullUrl(),
                    'user_id' => auth()->id(),
                ]);
            }
        });
    }
}
