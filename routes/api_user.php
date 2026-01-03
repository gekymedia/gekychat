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
use App\Http\Controllers\Api\V1\StatusController;
use App\Http\Controllers\Api\V1\BroadcastingController;
use App\Http\Controllers\TypingController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ChatController;

/*
|--------------------------------------------------------------------------
| USER AUTH (OTP)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::post('/auth/phone', [AuthController::class, 'requestOtp']);
    Route::post('/auth/verify', [AuthController::class, 'verifyOtp']);
});

/*
|--------------------------------------------------------------------------
| USER API (Mobile / Web)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->group(function () {

    Route::get('/me', fn (Request $r) => $r->user());
    Route::put('/me', [\App\Http\Controllers\Api\V1\ProfileController::class, 'update']);

    // ==================== CONVERSATIONS ====================
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations/start', [ConversationController::class, 'start']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::post('/conversations/{id}/read', [MessageController::class, 'markConversationRead']);

    // ==================== MESSAGES ====================
    Route::get('/conversations/{id}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{id}/messages', [MessageController::class, 'store']);
    Route::post('/messages/{id}/read', [MessageController::class, 'markRead']);
    Route::post('/messages/{id}/react', [ReactionController::class, 'reactToMessage']);
    Route::post('/messages/{id}/forward', [MessageController::class, 'forward']);
    Route::put('/messages/{id}', [MessageController::class, 'update']);
    Route::delete('/messages/{id}', [MessageController::class, 'destroy']);

    // ==================== TYPING ====================
    Route::post('/conversations/{id}/typing', [TypingController::class, 'start']);
    Route::delete('/conversations/{id}/typing', [TypingController::class, 'stop']);

    // ==================== GROUPS ====================
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::get('/groups/{id}', [GroupController::class, 'show']);

    // ==================== GROUP MESSAGES ====================
    Route::get('/groups/{id}/messages', [GroupMessageController::class, 'index']);
    Route::post('/groups/{id}/messages', [GroupMessageController::class, 'store']);

    // ==================== CONTACTS ====================
    Route::get('/contacts', [ContactsController::class, 'index']);
    Route::post('/contacts/sync', [ContactsController::class, 'sync']);
    Route::post('/contacts/resolve', [ContactsController::class, 'resolve']);

    // ==================== STATUS/STORIES ====================
    Route::get('/statuses', [StatusController::class, 'index']);
    Route::get('/statuses/mine', [StatusController::class, 'mine']);
    Route::get('/statuses/user/{userId}', [StatusController::class, 'userStatus']);
    Route::post('/statuses', [StatusController::class, 'store']);
    Route::post('/statuses/{id}/view', [StatusController::class, 'view']);
    Route::get('/statuses/{id}/viewers', [StatusController::class, 'viewers']);
    Route::delete('/statuses/{id}', [StatusController::class, 'destroy']);
    
    // Status Privacy
    Route::get('/statuses/privacy', [StatusController::class, 'getPrivacy']);
    Route::put('/statuses/privacy', [StatusController::class, 'updatePrivacy']);
    
    // Status Mute/Unmute
    Route::post('/statuses/user/{userId}/mute', [StatusController::class, 'muteUser']);
    Route::post('/statuses/user/{userId}/unmute', [StatusController::class, 'unmuteUser']);

    // ==================== UPLOADS ====================
    Route::post('/attachments', [AttachmentController::class, 'upload']);

    // ==================== NOTIFICATIONS (FCM) ====================
    Route::post('/notifications/register', [DeviceController::class, 'register']);
    Route::delete('/notifications/register', [DeviceController::class, 'unregister']);

    // ==================== BROADCASTING (PUSHER AUTH) ====================
    Route::post('/broadcasting/auth', [BroadcastingController::class, 'auth']);

    // ==================== CALLS ====================
    // Note: These routes also exist in web.php for session-based web auth
    // The routes here use auth:sanctum for API clients
    Route::post('/calls/start', [CallController::class, 'start']);
    Route::post('/calls/{session}/signal', [CallController::class, 'signal']);
    Route::post('/calls/{session}/end', [CallController::class, 'end']);
    Route::get('/calls/join/{callId}', [CallController::class, 'join']);
    
    // ==================== LABELS ====================
    Route::get('/labels', [\App\Http\Controllers\Api\V1\LabelController::class, 'index']);
    Route::post('/labels', [\App\Http\Controllers\Api\V1\LabelController::class, 'store']);
    Route::delete('/labels/{labelId}', [\App\Http\Controllers\Api\V1\LabelController::class, 'destroy']);
    
    // ==================== SEARCH ====================
    Route::get('/search', [SearchController::class, 'index']);
    Route::get('/search/filters', [SearchController::class, 'searchFilters']);
    
    // ==================== QUICK REPLIES ====================
    Route::get('/quick-replies', [\App\Http\Controllers\Api\V1\QuickReplyController::class, 'index']);
    Route::post('/quick-replies', [\App\Http\Controllers\Api\V1\QuickReplyController::class, 'store']);
    Route::put('/quick-replies/{id}', [\App\Http\Controllers\Api\V1\QuickReplyController::class, 'update']);
    Route::delete('/quick-replies/{id}', [\App\Http\Controllers\Api\V1\QuickReplyController::class, 'destroy']);
    Route::post('/quick-replies/{id}/usage', [\App\Http\Controllers\Api\V1\QuickReplyController::class, 'recordUsage']);
    
    // ==================== BLOCKS ====================
    Route::post('/blocks/{userId}', [\App\Http\Controllers\Api\V1\BlockController::class, 'block']);
    Route::delete('/blocks/{userId}', [\App\Http\Controllers\Api\V1\BlockController::class, 'unblock']);
    Route::get('/blocks', [\App\Http\Controllers\Api\V1\BlockController::class, 'index']);
    
    // ==================== REPORTS ====================
    Route::post('/reports/{userId}', [\App\Http\Controllers\Api\V1\ReportController::class, 'report']);
    
    // ==================== CONVERSATION ACTIONS ====================
    Route::post('/conversations/{id}/pin', [\App\Http\Controllers\Api\V1\ConversationController::class, 'pin']);
    Route::delete('/conversations/{id}/pin', [\App\Http\Controllers\Api\V1\ConversationController::class, 'unpin']);
    Route::post('/conversations/{id}/mark-unread', [\App\Http\Controllers\Api\V1\ConversationController::class, 'markUnread']);
    
    // ==================== LOCATION SHARING ====================
    Route::post('/conversations/{id}/share-location', [\App\Http\Controllers\Api\V1\MessageController::class, 'shareLocation']);
    Route::post('/groups/{id}/share-location', [\App\Http\Controllers\Api\V1\GroupMessageController::class, 'shareLocation']);
    
    // ==================== CONTACT SHARING ====================
    Route::post('/conversations/{id}/share-contact', [\App\Http\Controllers\Api\V1\MessageController::class, 'shareContact']);
    Route::post('/groups/{id}/share-contact', [\App\Http\Controllers\Api\V1\GroupMessageController::class, 'shareContact']);
});
