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
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PrivacySettingsController;
use App\Http\Controllers\Api\V1\NotificationPreferencesController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\MentionController;
use App\Http\Controllers\Admin\BadgeController;
use App\Http\Controllers\TypingController;
use App\Http\Controllers\RecordingController;
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
    Route::post('/auth/qr-login', [AuthController::class, 'qrLogin']);
    Route::post('/auth/qr-authenticate', [AuthController::class, 'authenticateQrSession']); // Authenticate web QR session from mobile app
    
    // PHASE 2: Feature Flags (accessible without auth - returns empty if not authenticated)
    Route::get('/feature-flags', [\App\Http\Controllers\Api\V1\FeatureFlagController::class, 'index']);
    
    // PHASE 2: Multi-account support (mobile/desktop only)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/accounts', [AuthController::class, 'getAccounts']);
        Route::post('/auth/switch-account', [AuthController::class, 'switchAccount']);
        Route::delete('/auth/accounts/{accountId}', [AuthController::class, 'removeAccount']);
        Route::get('/auth/qr-code', [AuthController::class, 'generateQrCode']); // Generate QR code for login
    });
});

/*
|--------------------------------------------------------------------------
| USER API (Mobile / Web)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    // Pusher webhook (no auth - called by Pusher servers)
    Route::post('/webhooks/pusher', [\App\Http\Controllers\Api\V1\PusherWebhookController::class])->name('webhooks.pusher');
});

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->group(function () {

    // Health check (for connectivity detection)
    Route::get('/health', [HealthController::class, 'index']);
    
    Route::get('/me', fn (Request $r) => $r->user());
    Route::put('/me', [\App\Http\Controllers\Api\V1\ProfileController::class, 'update']);
    Route::put('/user/dob', [\App\Http\Controllers\UserController::class, 'updateDob']);

    // ==================== CONVERSATIONS ====================
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations/start', [ConversationController::class, 'start']);
    // IMPORTANT: /conversations/archived must come BEFORE /conversations/{id} to avoid route conflict
    Route::get('/conversations/archived', [ConversationController::class, 'archived']);
    Route::get('/conversations/updated-since/{timestamp}', [ConversationController::class, 'updatedSince']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::post('/conversations/{id}/read', [MessageController::class, 'markConversationRead']);
    // ✅ MODERN: Bulk mark-as-read for multiple conversations
    Route::post('/conversations/bulk-read', [\App\Http\Controllers\Api\V1\ConversationController::class, 'bulkMarkAsRead']);
    Route::post('/conversations/{id}/pin', [ConversationController::class, 'pin']);
    Route::delete('/conversations/{id}/pin', [ConversationController::class, 'unpin']);
    
    // ✅ MODERN: Health check endpoints
    Route::get('/health', [\App\Http\Controllers\Api\V1\HealthController::class, 'index'])->withoutMiddleware(['auth:sanctum']);
    Route::get('/health/detailed', [\App\Http\Controllers\Api\V1\HealthController::class, 'detailed']);

    // ==================== SYNC (delta / changes) ====================
    Route::get('/sync/delta', [\App\Http\Controllers\Api\V1\SyncController::class, 'delta']);
    Route::get('/sync/changes', [\App\Http\Controllers\Api\V1\SyncController::class, 'changes']);

    // ==================== MESSAGES ====================
    Route::get('/conversations/{id}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{id}/messages', [MessageController::class, 'store']);
    Route::post('/messages/{id}/read', [MessageController::class, 'markRead']);
    // ✅ MODERN: Mark message as delivered (WhatsApp double-checkmark)
    Route::post('/messages/{id}/delivered', [MessageController::class, 'markDelivered']);
    Route::get('/messages/{id}/info', [MessageController::class, 'info']);
    Route::get('/messages/{id}/around', [MessageController::class, 'around']);
    Route::post('/messages/{id}/react', [ReactionController::class, 'reactToMessage']);
    Route::post('/messages/{id}/forward', [MessageController::class, 'forward']);
    Route::put('/messages/{id}', [MessageController::class, 'update']);
    Route::delete('/messages/{id}', [MessageController::class, 'destroy']);
    // Live location position updates
    Route::put('/messages/{id}/live-location', [MessageController::class, 'updateLiveLocation']);

    // ==================== DRAFT MESSAGES ====================
    Route::get('/drafts', [\App\Http\Controllers\Api\V1\DraftController::class, 'index']); // Get all drafts
    Route::get('/drafts/{conversationId}', [\App\Http\Controllers\Api\V1\DraftController::class, 'show']); // Get draft for conversation
    Route::post('/drafts/{conversationId}', [\App\Http\Controllers\Api\V1\DraftController::class, 'store']); // Save/update draft
    Route::delete('/drafts/{conversationId}', [\App\Http\Controllers\Api\V1\DraftController::class, 'destroy']); // Delete draft

    // ==================== TYPING ====================
    Route::post('/conversations/{id}/typing', [TypingController::class, 'start']);
    Route::delete('/conversations/{id}/typing', [TypingController::class, 'stop']);

    // ==================== RECORDING ====================
    Route::post('/conversations/{id}/recording', [RecordingController::class, 'start']);
    Route::delete('/conversations/{id}/recording', [RecordingController::class, 'stop']);

    // ==================== GROUPS ====================
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::get('/groups/{id}', [GroupController::class, 'show']);
    
    // ==================== CHANNELS (PHASE 2) ====================
    Route::get('/channels', [\App\Http\Controllers\Api\V1\ChannelController::class, 'index']);
    Route::post('/channels/{id}/follow', [\App\Http\Controllers\Api\V1\ChannelController::class, 'follow']);
    Route::post('/channels/{id}/unfollow', [\App\Http\Controllers\Api\V1\ChannelController::class, 'unfollow']);
    Route::get('/channels/{id}/posts', [\App\Http\Controllers\Api\V1\ChannelController::class, 'posts']);
    Route::post('/channels/{id}/posts', [\App\Http\Controllers\Api\V1\ChannelController::class, 'createPost']);
    Route::post('/channels/posts/{postId}/react', [\App\Http\Controllers\Api\V1\ChannelController::class, 'react']);
    Route::post('/groups/{id}/pin', [GroupController::class, 'pin']);
    Route::delete('/groups/{id}/pin', [GroupController::class, 'unpin']);

    // ==================== GROUP MESSAGES ====================
    Route::post('/groups/{id}/read', [GroupController::class, 'markAsRead']); // Mark group messages as read
    Route::get('/groups/{id}/messages', [GroupMessageController::class, 'index']);
    Route::post('/groups/{id}/messages', [GroupMessageController::class, 'store']);
    Route::get('/group-messages/{id}/info', [GroupMessageController::class, 'info']); // Group message info (readers, delivered, sent)
    Route::post('/groups/{groupId}/messages/{messageId}/reply-private', [GroupMessageController::class, 'replyPrivate']);
    Route::post('/group-messages/{id}/react', [ReactionController::class, 'reactToGroupMessage']);

    // ==================== CONTACTS ====================
    Route::get('/contacts', [ContactsController::class, 'index']);
    Route::post('/contacts', [ContactsController::class, 'store']);
    Route::post('/contacts/sync', [ContactsController::class, 'sync']);
    Route::post('/contacts/resolve', [ContactsController::class, 'resolve']);
    // By-username must come BEFORE {userId} so "by-username" is not captured as userId
    Route::get('/contacts/user/by-username/{username}/profile', [ContactsController::class, 'getUserProfileByUsername']);
    Route::get('/contacts/user/{userId}/profile', [ContactsController::class, 'getUserProfile']);

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
    
    // Status Comments
    Route::get('/statuses/{statusId}/comments', [\App\Http\Controllers\Api\V1\StatusCommentController::class, 'index']);
    Route::post('/statuses/{statusId}/comments', [\App\Http\Controllers\Api\V1\StatusCommentController::class, 'store']);
    Route::delete('/statuses/{statusId}/comments/{commentId}', [\App\Http\Controllers\Api\V1\StatusCommentController::class, 'destroy']);
    
    // Status Download
    Route::get('/statuses/{id}/download', [StatusController::class, 'download']);

    // ==================== UPLOADS ====================
    Route::post('/attachments', [AttachmentController::class, 'upload']);
    
    // ==================== UPLOAD LIMITS ====================
    Route::get('/upload-limits', [\App\Http\Controllers\Api\V1\UploadLimitsController::class, 'index']);
    Route::get('/attachments/{id}', [AttachmentController::class, 'show']); // MEDIA COMPRESSION: Check attachment status

    // ==================== NOTIFICATIONS (FCM) ====================
    Route::post('/notifications/register', [DeviceController::class, 'register']);
    Route::delete('/notifications/register', [DeviceController::class, 'unregister']);

    // ==================== BROADCASTING (PUSHER AUTH) ====================
    Route::post('/broadcasting/auth', [BroadcastingController::class, 'auth']);

    // ==================== AI CHAT ====================
    // Find or create conversation with AI bot (gekychat bot)
    Route::get('/ai/conversation', [\App\Http\Controllers\Api\Platform\ConversationController::class, 'findOrCreate']);

    // ==================== CALLS ====================
    // Note: These routes also exist in web.php for session-based web auth
    // The routes here use auth:sanctum for API clients
    Route::get('/calls', [\App\Http\Controllers\Api\V1\CallLogController::class, 'index']);
    Route::get('/calls/config', [CallController::class, 'config']); // PHASE 1: TURN server config
    Route::get('/webrtc/config', [\App\Http\Controllers\Api\V1\WebRtcController::class, 'getConfig']); // WebRTC TURN/ICE config
    Route::get('/calls/join/{callId}', [CallController::class, 'join']); // Existing web join route
    
    // PHASE 2: Group calls and meetings
    Route::post('/calls/{sessionId}/join-call', [CallController::class, 'joinCall']); // API join endpoint
    Route::post('/calls/{sessionId}/leave', [CallController::class, 'leave']);
    Route::get('/calls/{sessionId}/participants', [CallController::class, 'participants']);
    Route::post('/calls/{sessionId}/invite-link', [CallController::class, 'generateInviteLink']);
    // LiveKit SFU group calls — generates a signed JWT so the client can connect to the LiveKit server
    Route::get('/calls/livekit-token', [\App\Http\Controllers\Api\V1\LiveKitController::class, 'token']);
    
    // ==================== LABELS ====================
    Route::get('/labels', [\App\Http\Controllers\Api\V1\LabelController::class, 'index']);
    Route::post('/labels', [\App\Http\Controllers\Api\V1\LabelController::class, 'store']);
    Route::put('/labels/{labelId}', [\App\Http\Controllers\Api\V1\LabelController::class, 'update']);
    Route::delete('/labels/{labelId}', [\App\Http\Controllers\Api\V1\LabelController::class, 'destroy']);
    Route::post('/labels/{labelId}/attach/{conversationId}', [\App\Http\Controllers\Api\V1\LabelController::class, 'attachToConversation']);
    Route::delete('/labels/{labelId}/detach/{conversationId}', [\App\Http\Controllers\Api\V1\LabelController::class, 'detachFromConversation']);
    
    // ==================== BROADCAST LISTS ====================
    Route::get('/broadcast-lists', [\App\Http\Controllers\Api\V1\BroadcastListController::class, 'index']);
    Route::post('/broadcast-lists', [\App\Http\Controllers\Api\V1\BroadcastListController::class, 'store']);
    Route::get('/broadcast-lists/{id}', [\App\Http\Controllers\Api\V1\BroadcastListController::class, 'show']);
    Route::get('/broadcast-lists/{id}/members', [\App\Http\Controllers\Api\V1\BroadcastListController::class, 'members']);
    Route::put('/broadcast-lists/{id}', [\App\Http\Controllers\Api\V1\BroadcastListController::class, 'update']);
    Route::delete('/broadcast-lists/{id}', [\App\Http\Controllers\Api\V1\BroadcastListController::class, 'destroy']);
    Route::post('/broadcast-lists/{id}/send', [\App\Http\Controllers\Api\V1\BroadcastListController::class, 'sendMessage']);
    
    // ==================== TWO-FACTOR AUTHENTICATION ====================
    Route::get('/two-factor/status', [\App\Http\Controllers\Api\V1\TwoFactorController::class, 'status']);
    Route::get('/two-factor/setup', [\App\Http\Controllers\Api\V1\TwoFactorController::class, 'setup']);
    Route::post('/two-factor/enable', [\App\Http\Controllers\Api\V1\TwoFactorController::class, 'enable']);
    Route::post('/two-factor/disable', [\App\Http\Controllers\Api\V1\TwoFactorController::class, 'disable']);
    Route::post('/two-factor/regenerate-recovery-codes', [\App\Http\Controllers\Api\V1\TwoFactorController::class, 'regenerateRecoveryCodes']);
    
    // ==================== LINKED DEVICES ====================
    Route::get('/linked-devices', [\App\Http\Controllers\Api\V1\LinkedDevicesController::class, 'index']);
    Route::post('/linked-devices/link', [\App\Http\Controllers\Api\V1\LinkedDevicesController::class, 'linkDevice']);
    Route::delete('/linked-devices/{id}', [\App\Http\Controllers\Api\V1\LinkedDevicesController::class, 'destroy']);
    Route::delete('/linked-devices/others', [\App\Http\Controllers\Api\V1\LinkedDevicesController::class, 'destroyOthers']);
    
    // ==================== SEARCH ====================
    Route::get('/search', [SearchController::class, 'index']);
    Route::get('/search/filters', [SearchController::class, 'searchFilters']);
    
    // ==================== QUICK REPLIES ====================
    Route::get('/quick-replies', [\App\Http\Controllers\Api\V1\QuickReplyController::class, 'index']);
    Route::post('/quick-replies', [\App\Http\Controllers\Api\V1\QuickReplyController::class, 'store']);
    Route::put('/quick-replies/{id}', [\App\Http\Controllers\Api\V1\QuickReplyController::class, 'update']);
    Route::delete('/quick-replies/{id}', [\App\Http\Controllers\Api\V1\QuickReplyController::class, 'destroy']);
    Route::post('/quick-replies/{id}/usage', [\App\Http\Controllers\Api\V1\QuickReplyController::class, 'recordUsage']);
    
    // ==================== AUTO-REPLY RULES ====================
    Route::get('/auto-replies', [\App\Http\Controllers\Api\V1\AutoReplyController::class, 'index']);
    Route::post('/auto-replies', [\App\Http\Controllers\Api\V1\AutoReplyController::class, 'store']);
    Route::get('/auto-replies/{id}', [\App\Http\Controllers\Api\V1\AutoReplyController::class, 'show']);
    Route::put('/auto-replies/{id}', [\App\Http\Controllers\Api\V1\AutoReplyController::class, 'update']);
    Route::delete('/auto-replies/{id}', [\App\Http\Controllers\Api\V1\AutoReplyController::class, 'destroy']);
    
    // ==================== BLOCKS ====================
    Route::post('/blocks/{userId}', [\App\Http\Controllers\Api\V1\BlockController::class, 'block']);
    Route::delete('/blocks/{userId}', [\App\Http\Controllers\Api\V1\BlockController::class, 'unblock']);
    Route::get('/blocks', [\App\Http\Controllers\Api\V1\BlockController::class, 'index']);
    
    // ==================== REPORTS ====================
    Route::post('/reports/{userId}', [\App\Http\Controllers\Api\V1\ReportController::class, 'report']);
    
    // ==================== CONVERSATION ACTIONS ====================
    Route::post('/conversations/{id}/pin', [\App\Http\Controllers\Api\V1\ConversationController::class, 'pin']);
    Route::delete('/conversations/{id}/pin', [\App\Http\Controllers\Api\V1\ConversationController::class, 'unpin']);
    Route::post('/conversations/{id}/mute', [\App\Http\Controllers\Api\V1\ConversationController::class, 'mute']);
    Route::post('/conversations/{id}/mark-unread', [\App\Http\Controllers\Api\V1\ConversationController::class, 'markUnread']);
    Route::post('/conversations/{id}/archive', [\App\Http\Controllers\Api\V1\ConversationController::class, 'archive']);
    Route::delete('/conversations/{id}/archive', [\App\Http\Controllers\Api\V1\ConversationController::class, 'unarchive']);
    // Note: /conversations/archived route is defined earlier (before /conversations/{id}) to avoid route conflict
    
    // ==================== LOCATION SHARING ====================
    Route::post('/conversations/{id}/share-location', [\App\Http\Controllers\Api\V1\MessageController::class, 'shareLocation']);
    Route::post('/groups/{id}/share-location', [\App\Http\Controllers\Api\V1\GroupMessageController::class, 'shareLocation']);
    
    // ==================== CONTACT SHARING ====================
    Route::post('/conversations/{id}/share-contact', [\App\Http\Controllers\Api\V1\MessageController::class, 'shareContact']);
    Route::post('/groups/{id}/share-contact', [\App\Http\Controllers\Api\V1\GroupMessageController::class, 'shareContact']);
    
    // ==================== MEDIA GALLERY ====================
    Route::get('/conversations/{id}/media', [\App\Http\Controllers\Api\V1\MediaController::class, 'conversationMedia']);
    Route::get('/groups/{id}/media', [\App\Http\Controllers\Api\V1\MediaController::class, 'groupMedia']);
    
    // ==================== SEARCH IN CHAT ====================
    Route::get('/conversations/{id}/search', [\App\Http\Controllers\Api\V1\MessageController::class, 'search']);
    Route::get('/groups/{id}/search', [\App\Http\Controllers\Api\V1\GroupMessageController::class, 'search']);
    
    // ==================== CHAT ACTIONS ====================
    Route::post('/conversations/{id}/clear', [\App\Http\Controllers\Api\V1\ConversationController::class, 'clear']);
    Route::delete('/conversations/{id}', [\App\Http\Controllers\Api\V1\ConversationController::class, 'destroy']);
    Route::get('/conversations/{id}/export', [\App\Http\Controllers\Api\V1\ConversationController::class, 'export']);
    
    // ==================== GROUP MANAGEMENT ====================
    Route::put('/groups/{id}', [\App\Http\Controllers\Api\V1\GroupController::class, 'update']);
    Route::get('/groups/{group}/members', [\App\Http\Controllers\Api\V1\GroupMembersController::class, 'index']);
    Route::post('/groups/{group}/members', [\App\Http\Controllers\Api\V1\GroupMembersController::class, 'addByPhones']);
    Route::post('/groups/{id}/members/{userId}/promote', [\App\Http\Controllers\Api\V1\GroupMembersController::class, 'promote']);
    Route::post('/groups/{id}/members/{userId}/demote', [\App\Http\Controllers\Api\V1\GroupMembersController::class, 'demote']);
    Route::delete('/groups/{id}/members/{userId}', [\App\Http\Controllers\Api\V1\GroupMembersController::class, 'remove']);
    Route::delete('/groups/{id}/leave', [\App\Http\Controllers\Api\V1\GroupController::class, 'leave']);
    
    // ==================== PRIVACY SETTINGS ====================
    Route::get('/privacy-settings', [\App\Http\Controllers\Api\V1\PrivacySettingsController::class, 'index']);
    Route::put('/privacy-settings', [\App\Http\Controllers\Api\V1\PrivacySettingsController::class, 'update']);
    
    // ==================== NOTIFICATION SETTINGS ====================
    Route::get('/notification-settings', [\App\Http\Controllers\Api\V1\NotificationSettingsController::class, 'index']);
    Route::put('/notification-settings', [\App\Http\Controllers\Api\V1\NotificationSettingsController::class, 'update']);
    Route::get('/conversations/notification-settings', [\App\Http\Controllers\Api\V1\ConversationController::class, 'getNotificationSettings']);
    Route::put('/conversations/{id}/notification-settings', [\App\Http\Controllers\Api\V1\ConversationController::class, 'updateNotificationSettings']);
    Route::put('/groups/{id}/notification-settings', [\App\Http\Controllers\Api\V1\GroupController::class, 'updateNotificationSettings']);
    Route::post('/groups/{id}/generate-invite', [\App\Http\Controllers\Api\V1\GroupController::class, 'generateInvite']);
    Route::get('/groups/{id}/invite-info', [\App\Http\Controllers\Api\V1\GroupController::class, 'getInviteInfo']);
    Route::put('/groups/{id}/message-lock', [\App\Http\Controllers\Api\V1\GroupController::class, 'toggleMessageLock']);
    
    // ==================== MEDIA AUTO-DOWNLOAD ====================
    Route::get('/media-auto-download', [\App\Http\Controllers\Api\V1\MediaAutoDownloadController::class, 'index']);
    Route::put('/media-auto-download', [\App\Http\Controllers\Api\V1\MediaAutoDownloadController::class, 'update']);
    
    // ==================== STORAGE USAGE ====================
    Route::get('/storage-usage', [\App\Http\Controllers\Api\V1\StorageUsageController::class, 'index']);
    
    // ==================== STARRED MESSAGES ====================
    Route::get('/starred-messages', [\App\Http\Controllers\Api\V1\StarredMessageController::class, 'index']);
    
    // ==================== WORLD FEED (PHASE 2) ====================
    Route::get('/world-feed', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'index']);
    Route::get('/world-feed/posts', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'index']); // Alias for posts endpoint
    Route::post('/world-feed/posts', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'createPost']);
    Route::put('/world-feed/posts/{postId}', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'updatePost']);
    Route::delete('/world-feed/posts/{postId}', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'deletePost']);
    Route::get('/world-feed/posts/{postId}/share-url', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'getShareUrl']);
    Route::post('/world-feed/posts/{postId}/like', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'like']);
    Route::get('/world-feed/posts/{postId}/comments', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'comments']);
    Route::post('/world-feed/posts/{postId}/comments', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'addComment']);
    Route::post('/world-feed/comments/{commentId}/like', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'likeComment']);
    Route::post('/world-feed/creators/{creatorId}/follow', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'followCreator']);
    Route::get('/world-feed/trending-hashtags', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'trendingHashtags']);
    
    // Audio Routes
    Route::prefix('audio')->group(function () {
        Route::get('/search', [\App\Http\Controllers\Api\V1\AudioController::class, 'search']);
        Route::get('/trending', [\App\Http\Controllers\Api\V1\AudioController::class, 'trending']);
        Route::get('/categories', [\App\Http\Controllers\Api\V1\AudioController::class, 'categories']);
        Route::get('/tags', [\App\Http\Controllers\Api\V1\AudioController::class, 'tags']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\AudioController::class, 'show']);
        Route::get('/{id}/preview', [\App\Http\Controllers\Api\V1\AudioController::class, 'preview']);
        Route::get('/{id}/similar', [\App\Http\Controllers\Api\V1\AudioController::class, 'similar']);
        Route::post('/{id}/validate', [\App\Http\Controllers\Api\V1\AudioController::class, 'validateAudio']);
    });

    // ==================== EMAIL CHAT (PHASE 2) ====================
    Route::get('/mail', [\App\Http\Controllers\Api\V1\EmailChatController::class, 'index']);
    Route::get('/mail/check-username', [\App\Http\Controllers\Api\V1\EmailChatController::class, 'checkUsername']);
    Route::get('/mail/conversations/{id}/messages', [\App\Http\Controllers\Api\V1\EmailChatController::class, 'messages']);
    Route::post('/mail/messages/{messageId}/reply', [\App\Http\Controllers\Api\V1\EmailChatController::class, 'reply']);
    
    // ==================== LIVE BROADCAST (PHASE 2) ====================
    Route::post('/live/start', [\App\Http\Controllers\Api\V1\LiveBroadcastController::class, 'start']);
    Route::post('/live/{broadcastId}/join', [\App\Http\Controllers\Api\V1\LiveBroadcastController::class, 'join']);
    Route::post('/live/{broadcastId}/end', [\App\Http\Controllers\Api\V1\LiveBroadcastController::class, 'end']);
    Route::get('/live/active', [\App\Http\Controllers\Api\V1\LiveBroadcastController::class, 'active']);
    Route::post('/live/{broadcastId}/chat', [\App\Http\Controllers\Api\V1\LiveBroadcastController::class, 'sendChat']);
    
    // ==================== ACCOUNT ====================
    Route::delete('/account', [\App\Http\Controllers\Api\V1\AccountController::class, 'destroy']);
    
    // ==================== PRIVACY SETTINGS ====================
    Route::get('/privacy-settings', [PrivacySettingsController::class, 'index']);
    Route::put('/privacy-settings', [PrivacySettingsController::class, 'update']);
    
    // ==================== NOTIFICATION PREFERENCES ====================
    Route::get('/notification-preferences', [NotificationPreferencesController::class, 'index']);
    Route::put('/notification-preferences', [NotificationPreferencesController::class, 'update']);
    
    // ==================== AUDIT LOGS ====================
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    
    // ==================== MENTIONS ====================
    Route::get('/mentions', [MentionController::class, 'index']);
    Route::get('/mentions/stats', [MentionController::class, 'stats']);
    Route::post('/mentions/{id}/read', [MentionController::class, 'markAsRead']);
    Route::post('/mentions/read-all', [MentionController::class, 'markAllAsRead']);
    
    // ==================== FULL-TEXT SEARCH ====================
    Route::get('/search/messages', [\App\Http\Controllers\Api\V1\SearchMessagesController::class, 'search']);

    // ==================== PINNED MESSAGES ====================
    Route::post('/conversations/{id}/messages/{messageId}/pin', [MessageController::class, 'pinMessage']);
    Route::delete('/conversations/{id}/messages/pin', [MessageController::class, 'unpinMessage']);
    Route::get('/conversations/{id}/pinned-message', [MessageController::class, 'getPinnedMessage']);

    // ==================== VIEW ONCE ====================
    Route::post('/messages/{id}/view-once', [MessageController::class, 'markViewOnce']);

    // ==================== POLLS ====================
    Route::post('/conversations/{id}/polls', [MessageController::class, 'sendPoll']);
    Route::get('/polls/{pollId}', [\App\Http\Controllers\Api\V1\PollController::class, 'show']);
    Route::post('/polls/{pollId}/vote', [\App\Http\Controllers\Api\V1\PollController::class, 'vote']);

    // ==================== HASHTAG CHALLENGES ====================
    Route::prefix('challenges')->group(function () {
        Route::get('/',           [\App\Http\Controllers\Api\V1\ChallengeController::class, 'index']);
        Route::post('/',          [\App\Http\Controllers\Api\V1\ChallengeController::class, 'store']);
        Route::get('/trending',   [\App\Http\Controllers\Api\V1\ChallengeController::class, 'trending']);
        Route::get('/{id}',       [\App\Http\Controllers\Api\V1\ChallengeController::class, 'show']);
        Route::post('/{id}/join', [\App\Http\Controllers\Api\V1\ChallengeController::class, 'join']);
        Route::delete('/{id}/join',[\App\Http\Controllers\Api\V1\ChallengeController::class, 'leave']);
    });

    // ==================== TRENDING ====================
    Route::prefix('trending')->group(function () {
        Route::get('/hashtags', [\App\Http\Controllers\Api\V1\TrendingController::class, 'hashtags']);
        Route::get('/sounds',   [\App\Http\Controllers\Api\V1\TrendingController::class, 'sounds']);
        Route::get('/creators', [\App\Http\Controllers\Api\V1\TrendingController::class, 'creators']);
    });

    // ==================== ADMIN ROUTES ====================
    Route::middleware(['admin'])->prefix('admin')->group(function () {
        // Badge Management
        Route::get('/badges', [BadgeController::class, 'index']);
        Route::get('/users/{user}/badges', [BadgeController::class, 'userBadges']);
        Route::post('/users/{user}/badges', [BadgeController::class, 'assign']);
        Route::delete('/users/{user}/badges/{badge}', [BadgeController::class, 'remove']);
        
        // Audit Logs (all users)
        Route::get('/audit-logs', [AuditLogController::class, 'adminIndex']);
        Route::get('/audit-logs/statistics', [AuditLogController::class, 'statistics']);
    });
});
