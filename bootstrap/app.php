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
        then: function () {
            // Register landing page routes (main domain)
            require __DIR__ . '/../routes/landing.php';
        },
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
         \App\Http\Middleware\NoCacheHeaders::class,
    ]);
    
    // $middleware->api(append: [
    //     \App\Http\Middleware\UpdateLastSeen::class, // ADD THIS
    // ]);

    // Register custom route middleware aliases. This allows using `admin`
    // middleware in your route definitions to restrict access to admin users.
    $middleware->alias([
        'admin' => \App\Http\Middleware\Admin::class,
        'platform.api' => \App\Http\Middleware\AuthenticatePlatformApi::class,
    ]);
})
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();