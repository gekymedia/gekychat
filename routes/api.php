<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (api.gekychat.com)
|--------------------------------------------------------------------------
| These routes are accessible only on the API subdomain
*/

Route::domain(config('app.api_domain', 'api.gekychat.com'))->group(function () {
    // Root endpoint - show landing page for browsers, JSON for API clients
    Route::get('/', [\App\Http\Controllers\ApiLandingController::class, 'index']);

    // API Documentation page (needs web middleware for layouts)
    Route::middleware('web')->get('/docs', function () {
        return view('api.docs');
    })->name('api.docs');

    // Ping/Health check endpoint - no authentication required
    Route::get('/ping', function () {
        return response()->json([
            'status' => 'ok',
            'message' => 'API is working!',
            'timestamp' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'environment' => app()->environment(),
        ]);
    });

    require __DIR__.'/api_user.php';
    require __DIR__.'/api_platform.php';
});

// Also allow API routes on main domain (gekychat.com/api/*) as fallback
// Note: apiPrefix in bootstrap/app.php already adds /api prefix automatically
Route::group([], function () {
    // Root API endpoint for main domain - show landing page for browsers
    Route::get('/', [\App\Http\Controllers\ApiLandingController::class, 'index']);

    // API Documentation page (fallback on main domain - needs web middleware)
    Route::middleware('web')->get('/docs', function () {
        return view('api.docs');
    })->name('api.docs.fallback');

    // Ping/Health check endpoint - no authentication required
    Route::get('/ping', function () {
        return response()->json([
            'status' => 'ok',
            'message' => 'API is working!',
            'timestamp' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'environment' => app()->environment(),
        ]);
    });

    require __DIR__.'/api_user.php';
    require __DIR__.'/api_platform.php';
});
