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
use App\Http\Controllers\ChatController;

// Add this route - it should be accessible without auth first
Route::post('/generate-token', function (Request $request) {
    if (!auth()->check()) {
        return response()->json(['error' => 'Not authenticated'], 401);
    }

    $token = $request->user()->createToken('chat-token')->plainTextToken;
    return response()->json(['token' => $token]);
});

Route::prefix('v1')->middleware('auth')->group(function () {

    // ---------- Me ----------
    Route::get('/me', fn(Request $r) => \App\Support\ApiResponse::data($r->user()));

    // ---------- User Profile ----------
    Route::get('/users/{user}/profile', [ChatController::class, 'getUserProfile'])->name('api.user.profile');

    // ---------- Conversations (DM) ----------
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations/start', [ConversationController::class, 'start']); // by user_id or phone
    Route::get('/conversations/{id}', [ConversationController::class, 'show'])->whereNumber('id');
    Route::post('/conversations/{id}/pin', [ConversationController::class, 'pin'])->whereNumber('id');
    Route::delete('/conversations/{id}/pin', [ConversationController::class, 'unpin'])->whereNumber('id');
    Route::post('/conversations/{id}/mute', [ConversationController::class, 'mute'])->whereNumber('id');

    // Messages (DM)
    Route::get('/conversations/{id}/messages', [MessageController::class, 'index'])->whereNumber('id'); // ?before&after&limit
    Route::post('/conversations/{id}/messages', [MessageController::class, 'store'])->whereNumber('id'); // body / reply_to / forwarded_from_id / attachments[]
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

    // ---------- Enhanced Search ----------
    Route::prefix('search')->group(function () {
        // Main search with all features (contacts, users, groups, messages, phone detection)
        Route::get('/', [SearchController::class, 'index']);

        // Available search filters
        Route::get('/filters', [SearchController::class, 'searchFilters']);

        // Legacy search for backward compatibility
        Route::get('/legacy', [SearchController::class, 'legacySearch']);

        // Filter-specific searches
        Route::get('/contacts', [SearchController::class, 'searchContacts']);
        Route::get('/messages', [SearchController::class, 'searchMessages']);
        Route::get('/users', [SearchController::class, 'searchUsers']);
        Route::get('/groups', [SearchController::class, 'searchGroups']);
    });

    // Start chat with phone number (WhatsApp-like functionality)
    Route::post('/start-chat-with-phone', [SearchController::class, 'startChatWithPhone']);

    // ---------- Uploads ----------
    Route::post('/attachments', [AttachmentController::class, 'upload']);

    // ---------- Devices ----------
    Route::post('/devices', [DeviceController::class, 'register']);

    // ---------- Contacts (Telegram-style) ----------
    Route::get('/contacts', [ContactsController::class, 'index']);
    Route::post('/contacts', [ContactsController::class, 'store']); // Fixed: Added missing store route
    Route::post('/contacts/sync', [ContactsController::class, 'sync']);
    Route::post('/contacts/resolve', [ContactsController::class, 'resolve']);

    // Individual contact management
    Route::get('/contacts/{contact}', [ContactsController::class, 'show'])->whereNumber('contact');
    Route::put('/contacts/{contact}', [ContactsController::class, 'update'])->whereNumber('contact');
    Route::delete('/contacts/{contact}', [ContactsController::class, 'destroy'])->whereNumber('contact');
    Route::post('/contacts/{contact}/favorite', [ContactsController::class, 'favorite'])->whereNumber('contact');
    Route::delete('/contacts/{contact}/favorite', [ContactsController::class, 'unfavorite'])->whereNumber('contact');

    // ---------- Calls ----------
    Route::post('/calls/start', [CallController::class, 'start']);
    Route::post('/calls/{session}/signal', [CallController::class, 'signal'])->whereNumber('session');
    Route::post('/calls/{session}/end', [CallController::class, 'end'])->whereNumber('session');

    // ---------- Contact User Profile ----------
    Route::get('/contacts/user/{user}/profile', [ContactsController::class, 'getUserProfile']);

    // ---------- Labels ----------
    Route::get('/labels', [\App\Http\Controllers\Api\V1\LabelController::class, 'index']);
    Route::post('/labels', [\App\Http\Controllers\Api\V1\LabelController::class, 'store']);
    Route::delete('/labels/{label}', [\App\Http\Controllers\Api\V1\LabelController::class, 'destroy'])->whereNumber('label');
    // Assign/detach labels to conversations
    Route::post('/conversations/{conversation}/labels/{label}', [\App\Http\Controllers\Api\V1\LabelController::class, 'attachToConversation'])->whereNumber('conversation')->whereNumber('label');
    Route::delete('/conversations/{conversation}/labels/{label}', [\App\Http\Controllers\Api\V1\LabelController::class, 'detachFromConversation'])->whereNumber('conversation')->whereNumber('label');

    // ---------- Blocking and reporting users ----------
    Route::post('/users/{user}/block', [\App\Http\Controllers\Api\V1\BlockController::class, 'block'])->whereNumber('user');
    Route::delete('/users/{user}/block', [\App\Http\Controllers\Api\V1\BlockController::class, 'unblock'])->whereNumber('user');
    Route::post('/users/{user}/report', [\App\Http\Controllers\Api\V1\ReportController::class, 'report'])->whereNumber('user');
    // Admin review of reports
    Route::get('/reports', [\App\Http\Controllers\Api\V1\ReportController::class, 'index']);
    Route::put('/reports/{report}', [\App\Http\Controllers\Api\V1\ReportController::class, 'update'])->whereNumber('report');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/user/about', [App\Http\Controllers\UserController::class, 'updateAbout']);
});