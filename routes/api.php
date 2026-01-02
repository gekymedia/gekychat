<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (api.gekychat.com)
|--------------------------------------------------------------------------
| These routes are accessible only on the API subdomain
*/

Route::domain(config('app.api_domain', 'api.gekychat.com'))->group(function () {
    // Root API endpoint - welcome/info page
    Route::get('/', function () {
        return response()->json([
            'name' => 'GekyChat API',
            'version' => '1.0.0',
            'status' => 'active',
            'endpoints' => [
                'v1' => '/api/v1',
                'platform' => '/api/platform',
            ],
            'documentation' => '/api/docs',
        ]);
    });

    require __DIR__.'/api_user.php';
    require __DIR__.'/api_platform.php';
});

// Also allow API routes on main domain (gekychat.com/api/*) as fallback
// Note: apiPrefix in bootstrap/app.php already adds /api prefix automatically
Route::group([], function () {
    // Root API endpoint for main domain
    Route::get('/', function () {
        return response()->json([
            'name' => 'GekyChat API',
            'version' => '1.0.0',
            'status' => 'active',
            'message' => 'API is available. For best experience, use api.gekychat.com',
            'endpoints' => [
                'v1' => '/api/v1',
                'platform' => '/api/platform',
            ],
            'documentation' => '/api/docs',
        ]);
    });

    require __DIR__.'/api_user.php';
    require __DIR__.'/api_platform.php';
});
