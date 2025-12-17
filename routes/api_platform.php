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
Route::prefix('platform')->group(function () {

    // OAuth-like token issue
    Route::post('/oauth/token', [OAuthController::class, 'issueToken']);
});

/*
|--------------------------------------------------------------------------
| PLATFORM API (External Systems)
| Supports both platform API clients (auth:api-client) and user API keys (auth:sanctum)
|--------------------------------------------------------------------------
*/
Route::prefix('platform')
    ->middleware(['auth:api-client,sanctum'])
    ->group(function () {

    // User management
    Route::get('/users/by-phone', [\App\Http\Controllers\Api\Platform\UserController::class, 'findByPhone']);
    Route::post('/users', [\App\Http\Controllers\Api\Platform\UserController::class, 'create']);

    // Conversation management
    Route::get('/conversations/find-or-create', [\App\Http\Controllers\Api\Platform\ConversationController::class, 'findOrCreate']);

    // Send message as BOT / SYSTEM (conversation_id in request body)
    Route::post('/messages/send', [MessageController::class, 'send']);

    // Send message to phone number directly (auto-creates user/conversation for privileged clients)
    Route::post('/messages/send-to-phone', [\App\Http\Controllers\Api\Platform\SendMessageController::class, 'sendToPhone']);

    // Delivery status
    Route::get('/messages/{id}/status', [MessageController::class, 'status']);

    // Webhooks
    Route::post('/webhooks/incoming', [WebhookController::class, 'handle']);
});
