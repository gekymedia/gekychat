<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V2;

/*
|--------------------------------------------------------------------------
| API V2 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v2')->middleware(['api', 'api.version:v2'])->group(function () {
    
    // Authentication
    Route::post('/auth/login', [V2\AuthController::class, 'login']);
    Route::post('/auth/logout', [V2\AuthController::class, 'logout'])->middleware('auth:sanctum');
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        
        // User
        Route::get('/user', [V2\UserController::class, 'show']);
        Route::put('/user', [V2\UserController::class, 'update']);
        Route::put('/user/locale', [V2\UserController::class, 'updateLocale']);
        
        // Conversations
        Route::get('/conversations', [V2\ConversationController::class, 'index']);
        Route::get('/conversations/{id}', [V2\ConversationController::class, 'show']);
        Route::post('/conversations', [V2\ConversationController::class, 'store']);
        Route::delete('/conversations/{id}', [V2\ConversationController::class, 'destroy']);
        Route::get('/conversations/{id}/export', [V2\ConversationController::class, 'export']);
        
        // Messages
        Route::get('/conversations/{id}/messages', [V2\MessageController::class, 'index']);
        Route::post('/messages', [V2\MessageController::class, 'store']);
        Route::put('/messages/{id}', [V2\MessageController::class, 'update']);
        Route::delete('/messages/{id}', [V2\MessageController::class, 'destroy']);
        Route::post('/messages/{id}/react', [V2\MessageController::class, 'react']);
        
        // Contacts
        Route::get('/contacts', [V2\ContactController::class, 'index']);
        Route::post('/contacts', [V2\ContactController::class, 'store']);
        Route::delete('/contacts/{id}', [V2\ContactController::class, 'destroy']);
        
        // Statuses
        Route::get('/statuses', [V2\StatusController::class, 'index']);
        Route::post('/statuses', [V2\StatusController::class, 'store']);
        Route::post('/statuses/{id}/view', [V2\StatusController::class, 'view']);
        
    });
});
