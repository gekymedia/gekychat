<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Platform\OAuthController;
use App\Http\Controllers\Api\Platform\MessageController;
use App\Http\Controllers\Api\Platform\WebhookController;

/*
|--------------------------------------------------------------------------
| PLATFORM AUTH (CLIENT CREDENTIALS)
|--------------------------------------------------------------------------
*/
Route::prefix('api/platform')->group(function () {

    // OAuth-like token issue
    Route::post('/oauth/token', [OAuthController::class, 'issueToken']);
});

/*
|--------------------------------------------------------------------------
| PLATFORM API (External Systems)
|--------------------------------------------------------------------------
*/
Route::prefix('api/platform')
    ->middleware('auth:api-client')
    ->group(function () {

    // Send message as BOT / SYSTEM
    Route::post('/messages/send', [MessageController::class, 'send']);

    // Delivery status
    Route::get('/messages/{id}/status', [MessageController::class, 'status']);

    // Webhooks
    Route::post('/webhooks/incoming', [WebhookController::class, 'handle']);
});
