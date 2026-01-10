<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WorldController;
use App\Http\Controllers\Webhook\BlackTaskWebhookController;

// Include API user routes (v1 authentication, messages, etc.)
require __DIR__ . '/api_user.php';

// Webhooks (no auth required, uses bearer token verification)
Route::prefix('webhooks')->group(function () {
    Route::post('/blacktask', [BlackTaskWebhookController::class, 'handle']);
});

// World Feed API routes
Route::middleware('auth:sanctum')->prefix('world')->group(function () {
    // Search
    Route::post('/search', [WorldController::class, 'search']);
    Route::get('/search/suggestions', [WorldController::class, 'searchSuggestions']);
    Route::get('/search/trending', [WorldController::class, 'searchTrending']);
    Route::post('/search/clicks', [WorldController::class, 'trackSearchClick']);
    
    // Comments
    Route::get('/posts/{postId}/comments', [WorldController::class, 'getComments']);
    Route::post('/posts/{postId}/comments', [WorldController::class, 'postComment']);
    Route::post('/comments/{commentId}/like', [WorldController::class, 'toggleCommentLike']);
    
    // Users
    Route::post('/users/{userId}/follow', [WorldController::class, 'followUser']);
    Route::get('/users/{userId}/posts', [WorldController::class, 'getUserPosts']);
});
