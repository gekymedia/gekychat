<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\GroupMessageController;
use App\Http\Controllers\Api\V1\GroupMembersController;
use App\Http\Controllers\Api\V1\ReactionController;
use App\Http\Controllers\Api\V1\ContactsController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\TypingController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ChatController;

/*
|--------------------------------------------------------------------------
| USER AUTH (OTP)
|--------------------------------------------------------------------------
*/
Route::prefix('api/v1')->group(function () {
    Route::post('/auth/phone', [AuthController::class, 'requestOtp']);
    Route::post('/auth/verify', [AuthController::class, 'verifyOtp']);
});

/*
|--------------------------------------------------------------------------
| USER API (Mobile / Web)
|--------------------------------------------------------------------------
*/
Route::prefix('api/v1')
    ->middleware('auth:sanctum')
    ->group(function () {

    Route::get('/me', fn (Request $r) => $r->user());

    // Conversations
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations/start', [ConversationController::class, 'start']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);

    // Messages
    Route::get('/conversations/{id}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{id}/messages', [MessageController::class, 'store']);
    Route::post('/messages/{id}/read', [MessageController::class, 'markRead']);
    Route::post('/messages/{id}/react', [ReactionController::class, 'reactToMessage']);

    // Typing
    Route::post('/conversations/{id}/typing', [TypingController::class, 'start']);
    Route::delete('/conversations/{id}/typing', [TypingController::class, 'stop']);

    // Groups
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::get('/groups/{id}', [GroupController::class, 'show']);

    // Group Messages
    Route::get('/groups/{id}/messages', [GroupMessageController::class, 'index']);
    Route::post('/groups/{id}/messages', [GroupMessageController::class, 'store']);

    // Contacts
    Route::get('/contacts', [ContactsController::class, 'index']);
    Route::post('/contacts/sync', [ContactsController::class, 'sync']);
    Route::post('/contacts/resolve', [ContactsController::class, 'resolve']);

    // Uploads
    Route::post('/attachments', [AttachmentController::class, 'upload']);

    // Calls
    Route::post('/calls/start', [CallController::class, 'start']);
});
