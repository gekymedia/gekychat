<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Broadcast auth route for API/mobile clients using Sanctum
        // This allows Bearer token authentication from the Flutter app
        Broadcast::routes(['middleware' => ['auth:sanctum']]);
        
        // Load channel authorization callbacks
        require base_path('routes/channels.php');
    }
}