<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Broadcast auth: support both web (session) and API (Sanctum Bearer) so
        // the browser app and mobile app can subscribe to private/presence channels
        Broadcast::routes([
            'middleware' => [\App\Http\Middleware\EnsureBroadcastAuth::class],
        ]);

        // Load channel authorization callbacks
        require base_path('routes/channels.php');
    }
}