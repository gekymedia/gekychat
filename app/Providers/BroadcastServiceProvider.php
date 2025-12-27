<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Laravel's default broadcast auth route - handles Pusher automatically
        Broadcast::routes(['middleware' => ['web', 'auth']]);
        
        // Load channel authorization callbacks
        require base_path('routes/channels.php');
    }
}