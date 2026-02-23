<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

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
            // Register API subdomain routes (without api prefix)
            Route::domain(config('app.api_domain', 'api.gekychat.com'))->group(function () {
                Route::get('/', [\App\Http\Controllers\ApiLandingController::class, 'index'])->name('api.landing');
                
                // API Documentation (accessible without /api prefix)
                Route::middleware('web')->get('/docs', function () {
                    return view('api.docs');
                })->name('api.docs.root');
                
                // Include Platform API routes (OAuth, messages, etc.) - accessible without /api prefix
                require __DIR__ . '/../routes/api_platform.php';
            });
            
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

    $middleware->api(append: [
        \App\Http\Middleware\GzipResponseMiddleware::class,
        \App\Http\Middleware\CorrelationIdMiddleware::class,
    ]);

    // Register custom route middleware aliases. This allows using `admin`
    // middleware in your route definitions to restrict access to admin users.
    $middleware->alias([
        'admin' => \App\Http\Middleware\Admin::class,
        'platform.api' => \App\Http\Middleware\AuthenticatePlatformApi::class,
        'cache.headers' => \App\Http\Middleware\CacheControlMiddleware::class,
    ]);
})
    ->withExceptions(function (Exceptions $exceptions) {
        // Return structured ErrorResponse for API when abort() or validation fails
        $exceptions->render(function (Throwable $e, $request) {
            if (!$request->expectsJson() && !$request->is('api/*') && !str_starts_with($request->path(), 'api/')) {
                return null;
            }
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return \App\Http\Responses\ErrorResponse::validation($e->errors()->toArray());
            }
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: match ($status) {
                    403 => 'Forbidden',
                    404 => 'Not found',
                    422 => 'Unprocessable entity',
                    409 => 'Conflict',
                    default => 'Error',
                };
                return \App\Http\Responses\ErrorResponse::create(
                    match ($status) {
                        401 => \App\Http\Responses\ErrorResponse::ERROR_UNAUTHORIZED,
                        403 => \App\Http\Responses\ErrorResponse::ERROR_FORBIDDEN,
                        404 => \App\Http\Responses\ErrorResponse::ERROR_NOT_FOUND,
                        409 => \App\Http\Responses\ErrorResponse::ERROR_CONFLICT,
                        422 => \App\Http\Responses\ErrorResponse::ERROR_VALIDATION,
                        default => \App\Http\Responses\ErrorResponse::ERROR_SERVER,
                    },
                    $message,
                    null,
                    $status
                );
            }
            return null;
        });
    })
    ->create();