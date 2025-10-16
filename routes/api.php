<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ----- Controllers (API v1) -----
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\MessageReceiptController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\TypingController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\GroupMessageController;
use App\Http\Controllers\Api\V1\GroupMembersController;
use App\Http\Controllers\Api\V1\ReactionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Api\V1\ContactsController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\CallController;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // ---------- Me ----------
    Route::get('/me', fn(Request $r) => \App\Support\ApiResponse::data($r->user()));

    // ---------- Conversations (DM) ----------
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations/start', [ConversationController::class, 'start']); // by user_id or phone
    Route::get('/conversations/{id}', [ConversationController::class, 'show'])->whereNumber('id');
    Route::post('/conversations/{id}/pin', [ConversationController::class, 'pin'])->whereNumber('id');
    Route::delete('/conversations/{id}/pin', [ConversationController::class, 'unpin'])->whereNumber('id');
    Route::post('/conversations/{id}/mute', [ConversationController::class, 'mute'])->whereNumber('id');

    // Messages (DM)
    Route::get('/conversations/{id}/messages', [MessageController::class, 'index'])->whereNumber('id'); // ?before&after&limit
    Route::post('/conversations/{id}/messages', [MessageController::class, 'store'])->whereNumber('id'); // body / reply_to_id / forwarded_from_id / attachments[]
    Route::delete('/messages/{id}', [MessageController::class, 'destroy'])->whereNumber('id');
    Route::post('/messages/{id}/read', [MessageController::class, 'markRead'])->whereNumber('id');
    Route::post('/messages/{id}/react', [ReactionController::class, 'reactToMessage'])->whereNumber('id');
    Route::delete('/messages/{id}/react', [ReactionController::class, 'unreactFromMessage'])->whereNumber('id');
    Route::post('/messages/{id}/forward/targets', [MessageController::class, 'forwardToTargets'])->whereNumber('id');

    // Typing (DM)
    Route::post('/conversations/{id}/typing', [TypingController::class, 'start'])->whereNumber('id');
    Route::delete('/conversations/{id}/typing', [TypingController::class, 'stop'])->whereNumber('id');

    // ---------- Groups ----------
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']); // name, description?, member_ids[], avatar?
    Route::get('/groups/{id}', [GroupController::class, 'show'])->whereNumber('id');

    // Group messages
    Route::get('/groups/{id}/messages', [GroupMessageController::class, 'index'])->whereNumber('id'); // ?before&after&limit
    Route::post('/groups/{id}/messages', [GroupMessageController::class, 'store'])->whereNumber('id');
    Route::delete('/groups/{group}/messages/{message}', [GroupMessageController::class, 'destroy'])->whereNumber('group')->whereNumber('message');
    Route::post('/groups/{group}/messages/{message}/read', [GroupMessageController::class, 'markRead'])->whereNumber('group')->whereNumber('message');
    Route::post('/groups/{group}/messages/{message}/reactions', [ReactionController::class, 'reactToGroupMessage'])->whereNumber('group')->whereNumber('message');
    Route::post('/group-messages/{id}/forward/targets', [GroupMessageController::class, 'forwardToTargets'])->whereNumber('id');

    // Typing (Group)
    Route::post('/groups/{id}/typing', [GroupMessageController::class, 'typing'])->whereNumber('id');

    // Members
    Route::post('/groups/{group}/members/phones', [GroupMembersController::class, 'addByPhones'])->whereNumber('group');

    // Group membership management
    Route::delete('/groups/{group}/members/{user}', [GroupMembersController::class, 'remove'])
        ->whereNumber('group')->whereNumber('user');
    Route::post('/groups/{group}/members/{user}/promote', [GroupMembersController::class, 'promote'])
        ->whereNumber('group')->whereNumber('user');
    Route::post('/groups/{group}/members/{user}/demote', [GroupMembersController::class, 'demote'])
        ->whereNumber('group')->whereNumber('user');

    // ---------- Search ----------
    Route::get('/search', [SearchController::class, 'index']);

    // ---------- Uploads ----------
    Route::post('/attachments', [AttachmentController::class, 'upload']);

    // ---------- Devices ----------
    Route::post('/devices', [DeviceController::class, 'register']);

    // ---------- Contacts (Telegram-style) ----------
    Route::post('/contacts/sync',   [ContactsController::class, 'sync']);
    Route::get('/contacts',         [ContactsController::class, 'index']);
    Route::post('/contacts/resolve',[ContactsController::class, 'resolve']);

    // ---------- Calls ----------
    Route::post('/calls/start', [CallController::class, 'start']);
    Route::post('/calls/{session}/signal', [CallController::class, 'signal'])->whereNumber('session');
    Route::post('/calls/{session}/end', [CallController::class, 'end'])->whereNumber('session');
});
