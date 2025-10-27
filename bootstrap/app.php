<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Providers
use App\Providers\AppServiceProvider;
// use App\Providers\AuthServiceProvider;
// use App\Providers\EventServiceProvider;
use App\Providers\BroadcastServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',   // ✅ add this
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',                      // ✅ and this (final path will start with /api)
    )
    ->withProviders([
        AppServiceProvider::class,
        BroadcastServiceProvider::class,
        // AuthServiceProvider::class,
        // EventServiceProvider::class,
        
    ])
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\UpdateLastSeen::class,
    ]);
    
    $middleware->api(append: [
        \App\Http\Middleware\UpdateLastSeen::class, // ADD THIS
    ]);
})
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();