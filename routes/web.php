<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\PhoneVerificationController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\DirectChatController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\GoogleAuthController;
// use App\Http\Controllers\BroadcastAuthController; // Not needed - using Laravel's default broadcast auth
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\BlockController;
use App\Http\Controllers\Admin\ApiClientController;
use App\Http\Controllers\Admin\LogController;
// Quick Reply and Status Controllers (WEB features)
use App\Http\Controllers\QuickReplyController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\CallLogController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\Webhook\EmailWebhookController;

// BROADCAST AUTH: Using Laravel's default BroadcastServiceProvider route
// Custom route removed - Laravel's Broadcast::auth() handles Pusher automatically
// Route::post('/broadcasting/auth', [BroadcastAuthController::class, 'authenticate'])
//     ->middleware(['web', 'auth'])
//     ->name('broadcasting.auth');

// Route::get('/test-broadcast-setup', function () {
//     return response()->json([
//         'status' => 'ok',
//         'user' => auth()->user() ? auth()->user()->id : 'not logged in',
//         'broadcasting_config' => [
//             'driver' => config('broadcasting.default'),
//             'pusher_key' => config('broadcasting.connections.pusher.key'),
//             'pusher_host' => config('broadcasting.connections.pusher.options.host'),
//         ]
//     ]);
// })->middleware('auth');

/*
|--------------------------------------------------------------------------
| Web Routes (chat.gekychat.com)
|--------------------------------------------------------------------------
| These routes are accessible only on the chat subdomain
*/

Route::domain(config('app.chat_domain', 'chat.gekychat.com'))->group(function () {

// Logout (web session)
// Handle both GET and POST for logout
Route::match(['get', 'post'], '/logout', function (Request $request) {
    // Only logout if user is authenticated (prevents errors on GET requests)
    if (Auth::check()) {
        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
    // Always redirect to login page
    return redirect()->route('login');
})->name('logout');

/*
|--------------------------------------
| Guest-only Authentication (OTP / 2FA)
|--------------------------------------
*/
Route::middleware('guest')->group(function () {
    // Phone OTP flow
    Route::get('/login',           [PhoneVerificationController::class, 'show'])->name('login');
    Route::post('/send-otp',       [PhoneVerificationController::class, 'sendOtp'])->name('send.otp')->middleware('throttle:5,1');
    Route::get('/verify-otp',      [PhoneVerificationController::class, 'showOtpForm'])->name('verify.otp');
    Route::post('/verify-otp',     [PhoneVerificationController::class, 'verifyOtp'])->middleware('throttle:10,1');
    Route::post('/resend-otp',     [PhoneVerificationController::class, 'resendOtp'])->name('resend.otp')->middleware('throttle:5,1');

    // Two-Factor PIN verification flow
    Route::get('/verify-2fa',      [TwoFactorController::class, 'show'])->name('verify.2fa');
    Route::post('/verify-2fa',     [TwoFactorController::class, 'verify'])->middleware('throttle:10,1');
});

/*
|--------------------
| Authenticated App
|--------------------
*/
// API search endpoint for web interface (sidebar search) - must come before auth group
Route::middleware(['web', 'auth'])->prefix('api')->group(function () {
    Route::get('/search', [SearchController::class, 'index'])->name('api.search');
    Route::get('/search/filters', [SearchController::class, 'searchFilters'])->name('api.search.filters');
});

// Web API routes (using session auth) - must be before api_user.php routes
Route::middleware(['web', 'auth'])->prefix('api/v1')->group(function () {
    // Call routes for web interface
    Route::post('/calls/start', [\App\Http\Controllers\Api\V1\CallController::class, 'start'])->name('web.calls.start');
    Route::post('/calls/{session}/signal', [\App\Http\Controllers\Api\V1\CallController::class, 'signal'])->name('web.calls.signal');
    Route::post('/calls/{session}/end', [\App\Http\Controllers\Api\V1\CallController::class, 'end'])->name('web.calls.end');
});

// Call join route (public route with auth check inside)
Route::get('/calls/join/{callId}', [\App\Http\Controllers\Api\V1\CallController::class, 'join'])->name('calls.join');

Route::middleware('auth')->group(function () {
    // Location Sharing (Web)
    Route::post('/api/share-location', [ChatController::class, 'shareLocation'])->name('share-location');
    Route::post('/api/share-contact', [ChatController::class, 'shareContact'])->name('share-contact');
    
    // Contacts API for Web (session-based auth)
    Route::get('/api/contacts', function (Request $request) {
        $user = Auth::user();
        $contacts = \App\Models\Contact::with(['contactUser:id,name,phone,avatar_path,last_seen_at'])
            ->where('user_id', $user->id)
            ->where('is_deleted', false)
            ->orderByRaw('LOWER(COALESCE(NULLIF(display_name, ""), normalized_phone))')
            ->get()
            ->map(function ($contact) {
                $u = $contact->contactUser;
                return [
                    'id' => $contact->id,
                    'display_name' => $contact->display_name,
                    'phone' => $contact->phone,
                    'normalized_phone' => $contact->normalized_phone,
                    'email' => $contact->email,
                    'is_favorite' => (bool)$contact->is_favorite,
                    'is_registered' => !is_null($contact->contact_user_id),
                    'contact_user_id' => $contact->contact_user_id, // Add for mobile compatibility
                    'contact_user' => $u ? [ // Add for mobile compatibility
                        'id' => $u->id,
                        'name' => $u->name,
                        'phone' => $u->phone,
                        'avatar_url' => $u->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($u->avatar_path) : null,
                        'last_seen_at' => optional($u->last_seen_at)?->toISOString(),
                    ] : null,
                    'user_id' => $u?->id,
                    'user_name' => $u?->name,
                    'user_phone' => $u?->phone,
                    'avatar_url' => $u?->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($u->avatar_path) : null,
                    'last_seen_at' => optional($u?->last_seen_at)?->toISOString(),
                    'online' => $u?->last_seen_at && $u->last_seen_at->gt(now()->subMinutes(5)),
                ];
            });
        
        return response()->json([
            'data' => $contacts,
            'success' => true
        ]);
    })->name('api.contacts');

    /*
    |----------
    | Settings Routes
    |----------
    */
    // Quick Replies API Routes
    Route::get('/api/quick-replies', [QuickReplyController::class, 'getQuickReplies'])->name('api.quick-replies');
    Route::post('/api/quick-replies/{id}/record-usage', [QuickReplyController::class, 'recordUsage'])->name('api.quick-replies.record-usage');


    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::match(['PUT', 'POST'], '/', [SettingsController::class, 'update'])->name('update');
        Route::get('/theme', function () {
            return view('settings.theme');
        })->name('theme');
        Route::put('/profile', [SettingsController::class, 'updateProfile'])->name('profile');
        Route::put('/password', [SettingsController::class, 'updatePassword'])->name('password');

        // API Keys management routes
        Route::post('/api-keys/generate', [SettingsController::class, 'generateApiKey'])->name('api-keys.generate');
        Route::delete('/api-keys/{token}', [SettingsController::class, 'revokeApiKey'])->name('api-keys.revoke');

        Route::get('/quick-replies', [QuickReplyController::class, 'index'])->name('quick-replies');
        Route::post('/quick-replies', [QuickReplyController::class, 'store'])->name('quick-replies.store');
        Route::put('/quick-replies/{id}', [QuickReplyController::class, 'update'])->name('quick-replies.update');
        Route::delete('/quick-replies/{id}', [QuickReplyController::class, 'destroy'])->name('quick-replies.destroy');
        Route::post('/quick-replies/reorder', [QuickReplyController::class, 'reorder'])->name('quick-replies.reorder');

        // Devices & Sessions
        Route::get('/devices', [DeviceController::class, 'index'])->name('devices');
        Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::post('/devices/update-activity', [DeviceController::class, 'updateActivity'])->name('devices.update-activity');
        Route::delete('/devices/{session}', [DeviceController::class, 'destroy'])->name('devices.destroy');
        Route::delete('/devices', [DeviceController::class, 'destroyAllOther'])->name('devices.logout-all-other');

        // Multi-account support (web routes using session auth)
        Route::get('/auth/accounts', [\App\Http\Controllers\Api\V1\AuthController::class, 'getAccounts'])->name('auth.accounts');
        Route::post('/auth/switch-account', [\App\Http\Controllers\Api\V1\AuthController::class, 'switchAccount'])->name('auth.switch-account');
        Route::delete('/auth/accounts/{accountId}', [\App\Http\Controllers\Api\V1\AuthController::class, 'removeAccount'])->name('auth.accounts.remove');
    });
    
    // Call Logs
    Route::get('/calls', [CallLogController::class, 'index'])->name('calls.index');
    
    // Channels (user-facing, not admin)
    Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
    
    // PHASE 2: World Feed (web interface)
    Route::get('/world-feed', [\App\Http\Controllers\WorldFeedController::class, 'index'])->name('world-feed.index');
    
    // Audio Library
    Route::get('/audio/browse', [\App\Http\Controllers\AudioController::class, 'browse'])->name('audio.browse');
    
    // Audio AJAX routes (web-specific, not API)
    Route::prefix('audio')->name('audio.')->group(function () {
        Route::get('/search', [\App\Http\Controllers\Api\V1\AudioController::class, 'search'])->name('search');
        Route::get('/trending', [\App\Http\Controllers\Api\V1\AudioController::class, 'trending'])->name('trending');
    });
    
    // World Feed AJAX routes (web-specific, not API)
    Route::prefix('world-feed')->name('world-feed.')->group(function () {
        Route::get('/posts', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'index'])->name('posts');
        Route::post('/posts', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'createPost'])->name('posts.create');
        Route::post('/posts/{postId}/like', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'like'])->name('posts.like');
        Route::get('/posts/{postId}/comments', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'comments'])->name('posts.comments');
        Route::post('/posts/{postId}/comments', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'addComment'])->name('posts.comments.add');
        Route::post('/creators/{creatorId}/follow', [\App\Http\Controllers\Api\V1\WorldFeedController::class, 'followCreator'])->name('creators.follow');
    });
    
    // PHASE 2: World Feed Share URL (public route - no auth required)
    // Shows WhatsApp-style invite page
    Route::get('/wf/{code}', [\App\Http\Controllers\InviteController::class, 'show'])
        ->name('world-feed.share');
    
    // PHASE 2: Email Chat (web interface)
    Route::get('/email-chat', [\App\Http\Controllers\EmailChatController::class, 'index'])->name('email-chat.index');
    
    // Email Chat AJAX routes (web-specific, not API)
    Route::prefix('email-chat')->name('email-chat.')->group(function () {
        Route::get('/conversations', [\App\Http\Controllers\Api\V1\EmailChatController::class, 'index'])->name('conversations');
        Route::get('/conversations/{conversationId}/messages', [\App\Http\Controllers\Api\V1\EmailChatController::class, 'messages'])->name('messages');
        Route::post('/conversations/{conversationId}/reply', [\App\Http\Controllers\Api\V1\EmailChatController::class, 'reply'])->name('reply');
    });
    
    // PHASE 2: AI Chat (web interface)
    Route::get('/ai-chat', [\App\Http\Controllers\AiChatController::class, 'index'])->name('ai-chat.index');
    
    // PHASE 2: Live Broadcast (web interface)
    Route::get('/live-broadcast', [\App\Http\Controllers\LiveBroadcastController::class, 'index'])->name('live-broadcast.index');
    
    // Live Broadcast AJAX routes (web-specific, not API)
    // Note: specific routes must come before {broadcastId} route to avoid route conflict
    Route::prefix('live-broadcast')->name('live-broadcast.')->group(function () {
        Route::get('/active', [\App\Http\Controllers\Api\V1\LiveBroadcastController::class, 'active'])->name('active');
        Route::post('/start', [\App\Http\Controllers\Api\V1\LiveBroadcastController::class, 'start'])->name('start');
        Route::get('/{broadcastId}/info', [\App\Http\Controllers\Api\V1\LiveBroadcastController::class, 'show'])->name('info');
        Route::post('/{broadcastId}/join', [\App\Http\Controllers\Api\V1\LiveBroadcastController::class, 'join'])->name('join');
        Route::post('/{broadcastId}/end', [\App\Http\Controllers\Api\V1\LiveBroadcastController::class, 'end'])->name('end');
        Route::post('/{broadcastId}/chat', [\App\Http\Controllers\Api\V1\LiveBroadcastController::class, 'sendChat'])->name('chat');
        Route::get('/{broadcastId}', [\App\Http\Controllers\LiveBroadcastController::class, 'watch'])->name('watch');
    });
    
    // PHASE 2: Broadcast Lists (web interface)
    Route::prefix('broadcast-lists')->name('broadcast-lists.')->group(function () {
        Route::get('/', [\App\Http\Controllers\BroadcastListController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Http\Controllers\BroadcastListController::class, 'show'])->name('show');
        // AJAX routes (web-specific, not API) - must come before show route to avoid conflict
        Route::get('/api/list', [\App\Http\Controllers\Api\V1\BroadcastListController::class, 'index'])->name('api.list');
        Route::post('/', [\App\Http\Controllers\Api\V1\BroadcastListController::class, 'store'])->name('store');
        Route::put('/{id}', [\App\Http\Controllers\Api\V1\BroadcastListController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Api\V1\BroadcastListController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/send', [\App\Http\Controllers\Api\V1\BroadcastListController::class, 'sendMessage'])->name('send');
    });
    
    // Archive/Unarchive routes for web (session-based auth)
    Route::post('/api/conversations/{id}/archive', [\App\Http\Controllers\Api\V1\ConversationController::class, 'archive'])->name('conversations.archive');
    Route::delete('/api/conversations/{id}/archive', [\App\Http\Controllers\Api\V1\ConversationController::class, 'unarchive'])->name('conversations.unarchive');
});

// API Documentation
Route::get('/api/docs', function () {
    return view('api.docs');
})->name('api.docs.web');

/*
    |----------
    | Contacts Management
    |----------
    */
Route::prefix('contacts')->name('contacts.')->group(function () {
    Route::get('/', [ContactsController::class, 'index'])->name('index');
    Route::post('/', [ContactsController::class, 'store'])->name('store');
    Route::get('/create', [ContactsController::class, 'create'])->name('create');
    Route::get('/user/{user}/profile', [ContactsController::class, 'getUserProfile'])->name('user.profile');
    Route::get('/{contact}', [ContactsController::class, 'show'])->name('show');
    Route::put('/{contact}', [ContactsController::class, 'update'])->name('update');
    Route::delete('/{contact}', [ContactsController::class, 'destroy'])->name('destroy');
    Route::post('/{contact}/favorite', [ContactsController::class, 'favorite'])->name('favorite');
    Route::delete('/{contact}/favorite', [ContactsController::class, 'unfavorite'])->name('unfavorite');
    Route::post('/bulk-delete', [ContactsController::class, 'bulkDelete'])->name('bulk.delete');
    Route::post('/import-google', [ContactsController::class, 'importGoogle'])->name('import.google');
    Route::post('/sync-google', [ContactsController::class, 'syncGoogle'])->name('sync.google');
});

// Labels Management (Web Routes)
Route::middleware('auth')->prefix('labels')->name('labels.')->group(function () {
    Route::get('/', [\App\Http\Controllers\LabelController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\LabelController::class, 'store'])->name('store');
    Route::delete('/{label}', [\App\Http\Controllers\LabelController::class, 'destroy'])->name('destroy');
    Route::post('/{label}/attach/{conversation}', [\App\Http\Controllers\LabelController::class, 'attachToConversation'])->name('attach');
    Route::delete('/{label}/detach/{conversation}', [\App\Http\Controllers\LabelController::class, 'detachFromConversation'])->name('detach');
});

// Block management routes (separate from contacts for clarity)
Route::middleware('auth')->prefix('blocks')->name('blocks.')->group(function () {
    Route::post('/', [ContactsController::class, 'blockcontactstore'])->name('store');
    Route::delete('/{user}', [ContactsController::class, 'blockcontactdestroy'])->name('destroy');
});

/*
    |----------
    | Chat (DMs) - Shortened to /c
    |----------
    */
Route::middleware('auth')->prefix('c')->name('chat.')->group(function () {
    Route::get('/',                 [ChatController::class, 'index'])->name('index');
    Route::get('/new',              [ChatController::class, 'new'])->name('new');
    Route::get('/start',            [ChatController::class, 'start'])->name('start'); // GET route for query parameter
    Route::post('/start',           [ChatController::class, 'start'])->name('start.post'); // POST route for forms
    Route::post('/send',            [ChatController::class, 'send'])->name('send');
    Route::post('/read',            [ChatController::class, 'markAsRead'])->name('read');
    // Typing route - must be before /{conversation} route to avoid route conflicts
    Route::match(['POST', 'DELETE'], '/typing', [ChatController::class, 'typing'])->name('typing');
    Route::post('/clear/{conversation}', [ChatController::class, 'clear'])->name('clear');
    Route::get('/{conversation}',   [ChatController::class, 'show'])->name('show');
    Route::get('/{conversation}/history', [ChatController::class, 'history'])->name('history');
    Route::post('/forward/targets', [ChatController::class, 'forwardToTargets'])->name('forward.targets');
});

// DM reactions endpoint used by the frontend JS
Route::post('/messages/react', [ChatController::class, 'addReaction'])->name('messages.react');

// Delete a DM message (used by JS via /messages/{id})
Route::delete('/messages/{id}', [ChatController::class, 'deleteMessage'])
    ->whereNumber('id')->name('message.delete');
Route::put('/messages/{id}', [ChatController::class, 'editMessage'])
    ->whereNumber('id')->name('message.edit');

/*
    |----------
    | Enhanced Search Routes
    |----------
    */
Route::prefix('search')->name('search.')->group(function () {
    Route::get('/', [SearchController::class, 'index'])->name('index');
    Route::get('/filters', [SearchController::class, 'searchFilters'])->name('filters');
    Route::get('/contacts', [SearchController::class, 'searchContacts'])->name('contacts');
    Route::get('/messages', [SearchController::class, 'searchMessages'])->name('messages');
});


// Start chat with phone number (WhatsApp-like functionality)
Route::post('/start-chat-with-phone', [SearchController::class, 'startChatWithPhone'])
    ->name('chat.start-with-phone');

/*
    |----------
    | Profile
    |----------
    */
Route::get('/profile',  [ProfileController::class, 'edit'])->name('profile.edit');
Route::match(['PUT', 'POST'], '/profile', [ProfileController::class, 'update'])->name('profile.update');

/*
    |----------
    | Security / Settings
    |----------
    */
Route::get('/settings/security',  [SecurityController::class, 'show'])->name('settings.security.show');
Route::post('/settings/security', [SecurityController::class, 'update'])->name('settings.security.update');

/*
    |----------
    | Groups - Shortened to /g
    |----------
    */
Route::prefix('g')->name('groups.')->group(function () {
    Route::get('/{group}/messages/partial', [GroupController::class, 'messagesPartial'])
        ->name('messages.partial')
        ->middleware('auth');
    Route::get('/create',  [GroupController::class, 'create'])->name('create');
    Route::post('/',       [GroupController::class, 'store'])->name('store');
    Route::get('/invite/{invite_code}', [GroupController::class, 'join'])
        ->name('join')
        ->middleware('auth');
    Route::post('{group}/read', [GroupController::class, 'markAsRead'])->name('read');
    Route::post('/forward/targets', [GroupController::class, 'forwardToTargets'])->name('forward.targets');
    Route::get('/{group}', [GroupController::class, 'show'])->name('show');
    Route::get('/{group}/edit', [GroupController::class, 'edit'])->name('edit');
    Route::put('/{group}', [GroupController::class, 'updateGroup'])->name('update');
    Route::get('/{group}/history', [GroupController::class, 'history'])->name('messages.history');
    Route::post('/{group}/messages',                 [GroupController::class, 'sendMessage'])->name('messages.store');
    Route::put('/{group}/messages/{message}',        [GroupController::class, 'editMessage'])->name('messages.update');
    Route::delete('/{group}/messages/{message}',     [GroupController::class, 'deleteMessage'])->name('messages.delete');
    Route::post('/{group}/messages/{message}/reactions', [GroupController::class, 'addReaction'])->name('messages.reactions');

        // Allow replying privately to a group message
        // This GET route will find or create a direct conversation between the current user and the
        // sender of the specified group message, then redirect the user to the DM. It also
        // automatically inserts a placeholder message referencing the group message for context.
        Route::get('/{group}/messages/{message}/reply-private', [GroupController::class, 'replyPrivate'])
            ->name('messages.reply-private');
    Route::post('/{group}/members',          [GroupController::class, 'addMembers'])->name('members.add');
    Route::post('/{group}/members/{userId}/promote', [GroupController::class, 'promoteMember'])->name('members.promote');
    Route::delete('/{group}/members/{userId}', [GroupController::class, 'removeMember'])->name('members.remove');
    Route::post('/{group}/leave',            [GroupController::class, 'leave'])->name('leave');
    Route::post('/{group}/transfer',         [GroupController::class, 'transferOwnership'])->name('transfer');
    Route::post('/{group}/typing', [GroupController::class, 'typing'])->name('typing');
    Route::post('/{group}/generate-invite', [GroupController::class, 'generateInvite'])->name('generate-invite');
    Route::get('/{group}/invite-info', [GroupController::class, 'getInviteInfo'])->name('invite-info');
    Route::post('/{group}/revoke-invite', [GroupController::class, 'revokeInvite'])->name('revoke-invite');
    Route::post('/{group}/share-invite', [GroupController::class, 'shareInvite'])->name('share-invite');
    Route::post('/{group}/share-location', [GroupController::class, 'shareLocation'])->name('share-location');
    Route::post('/{group}/share-contact', [GroupController::class, 'shareContact'])->name('share-contact');
});

/*
    |-----------
    | Google OAuth Routes
    |-----------
    */
Route::prefix('auth/google')->name('google.')->group(function () {
    Route::get('/', [GoogleAuthController::class, 'redirect'])->name('auth');
    Route::get('/callback', [GoogleAuthController::class, 'callback'])->name('auth.callback');
});

/*
    |-----------
    | Quick Replies Routes (WEB FEATURE)
    |-----------
    */
Route::prefix('quick-replies')->name('quick-replies.')->group(function () {
    Route::get('/', [QuickReplyController::class, 'getQuickReplies'])->name('index');
    Route::post('/', [QuickReplyController::class, 'createQuickReply'])->name('store');
    Route::get('/search', [QuickReplyController::class, 'searchQuickReplies'])->name('search');
    Route::post('/{id}/record-usage', [QuickReplyController::class, 'recordUsage'])->name('record-usage');
    Route::delete('/{id}', [QuickReplyController::class, 'deleteQuickReply'])->name('delete');
});

/*
    |-----------
    | Status Routes (WEB FEATURE)
    |-----------
    */
Route::prefix('status')->name('status.')->group(function () {
    Route::get('/', [StatusController::class, 'getStatuses'])->name('index');
    Route::get('/user/{user}', [StatusController::class, 'getUserStatuses'])->name('user');
    Route::post('/', [StatusController::class, 'createStatus'])->name('store');
    Route::post('/{id}/view', [StatusController::class, 'viewStatus'])->name('view');
    Route::delete('/{id}', [StatusController::class, 'deleteStatus'])->name('delete');
    Route::get('/{id}/viewers', [StatusController::class, 'getStatusViewers'])->name('viewers');
});

// User Reporting (Web Route)
Route::middleware('auth')->prefix('users')->name('users.')->group(function () {
    Route::post('/{user}/report', [\App\Http\Controllers\ReportController::class, 'store'])->name('report');
});


/*
|-----------
| Admin Routes
|-----------
*/
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Main dashboard and analytics
        Route::get('/', [AdminController::class, 'index'])->name('dashboard');
        Route::get('/analytics/export', [AdminController::class, 'exportAnalytics'])->name('analytics.export');
        Route::get('/analytics/users', [AdminController::class, 'userAnalytics'])->name('analytics.users');
        Route::get('/api/refresh-data', [AdminController::class, 'refreshData'])->name('api.refresh-data');
        Route::get('/system/health', [AdminController::class, 'systemHealth'])->name('system.health');

        // User Management
        Route::get('/users', [AdminController::class, 'users'])->name('users.index');
        Route::get('/users/{user}/stats', [AdminController::class, 'getUserStats'])->name('users.stats');
        Route::post('/users/{user}/suspend', [AdminController::class, 'suspendUser'])->name('users.suspend');
        Route::post('/users/{user}/activate', [AdminController::class, 'activateUser'])->name('users.activate');
        Route::patch('/users/{user}/toggle-special-api-privilege', [AdminController::class, 'toggleSpecialApiPrivilege'])->name('users.toggle-special-api-privilege');

        // Reports Management
        Route::get('/reports', [AdminController::class, 'reportsIndex'])->name('reports.index');
        Route::match(['put', 'patch'], '/reports/{report}', [AdminController::class, 'reportsUpdate'])->name('reports.update');

        // Blocked Users Management
        Route::get('/blocks', [AdminController::class, 'blocksIndex'])->name('blocks.index');
        Route::post('/blocks/{user}', [AdminController::class, 'blocksBlock'])->name('blocks.block');
        Route::delete('/blocks/{user}', [AdminController::class, 'blocksUnblock'])->name('blocks.unblock');

        // API Clients Management
        Route::get('/api-clients', [AdminController::class, 'apiClientsIndex'])->name('api-clients.index');
        Route::get('/special-api-privileges', [AdminController::class, 'specialApiPrivileges'])->name('special-api-privileges.index');
        Route::put('/api-clients/{client}/status', [AdminController::class, 'apiClientsUpdateStatus'])->name('api-clients.status');
        
        // Email Logs
        Route::get('/email-logs', [\App\Http\Controllers\Admin\EmailLogsController::class, 'index'])->name('email-logs.index');
        Route::get('/email-logs/{id}', [\App\Http\Controllers\Admin\EmailLogsController::class, 'show'])->name('email-logs.show');
        Route::get('/email-logs/data', [\App\Http\Controllers\Admin\EmailLogsController::class, 'data'])->name('email-logs.data');
        Route::patch('/api-clients/{client}/status', [AdminController::class, 'apiClientsUpdateStatus'])->name('api-clients.update-status');
        Route::delete('/api-clients/{client}', [AdminController::class, 'apiClientsDestroy'])->name('api-clients.destroy');
        Route::patch('/api-clients/{client}/webhook', [AdminController::class, 'apiClientsUpdateWebhook'])->name('api-clients.update-webhook');
                        Route::patch('/api-clients/{client}/regenerate-secret', [AdminController::class, 'apiClientsRegenerateSecret'])->name('api-clients.regenerate-secret');
                        Route::get('/api-clients/{id}/details', [AdminController::class, 'apiClientsDetails'])->name('api-clients.details');
                        Route::patch('/api-clients/{id}/toggle-special-privilege', [AdminController::class, 'apiClientsToggleSpecialPrivilege'])->name('api-clients.toggle-special-privilege');

        // Settings
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::post('/settings/bot', [AdminController::class, 'updateBotSettings'])->name('settings.bot.update');

        // BlackTask Integration
        Route::get('/blacktask', [\App\Http\Controllers\Admin\BlackTaskIntegrationController::class, 'index'])->name('blacktask.index');
        Route::post('/blacktask/config', [\App\Http\Controllers\Admin\BlackTaskIntegrationController::class, 'updateConfig'])->name('blacktask.config');
        Route::get('/blacktask/test', [\App\Http\Controllers\Admin\BlackTaskIntegrationController::class, 'testConnection'])->name('blacktask.test');

        // Channels Management
        Route::get('/channels', [AdminController::class, 'channelsIndex'])->name('channels.index');
        Route::post('/channels/{group:id}/toggle-verified', [AdminController::class, 'toggleChannelVerified'])->name('channels.toggle-verified');
        
        // Audio Library Management
        Route::get('/audio', [AdminController::class, 'audioLibrary'])->name('audio.index');
        Route::post('/audio/{audio}/toggle-status', [AdminController::class, 'toggleAudioStatus'])->name('audio.toggle-status');
        Route::post('/audio/{audio}/validation', [AdminController::class, 'updateAudioValidation'])->name('audio.validation');

        // Legacy routes (for backward compatibility)
        Route::get('/reports-legacy', [AdminController::class, 'reports'])->name('reports.legacy');
        Route::get('/banned-users', [AdminController::class, 'bannedUsers'])->name('banned');
        Route::get('/apiclients', [AdminController::class, 'apiClients'])->name('api_clients.legacy');
        
        // Content Moderation
        Route::get('/messages', [AdminController::class, 'messages'])->name('messages');
        Route::get('/messages/{id}', [AdminController::class, 'showMessage'])->whereNumber('id')->name('message.show');
        Route::delete('/messages/{id}', [AdminController::class, 'delete'])->whereNumber('id')->name('message.delete');
        Route::get('/conversations', [AdminController::class, 'conversations'])->name('conversations');
        Route::get('/conversations/{id}', [AdminController::class, 'showConversation'])->whereNumber('id')->name('conversation.show');
        Route::delete('/conversations/{id}', [AdminController::class, 'deleteConversation'])->whereNumber('id')->name('conversation.delete');
        
        // PHASE 2: Phase Mode Control
        Route::get('/phase-mode', [\App\Http\Controllers\Admin\PhaseModeController::class, 'index'])->name('phase-mode.index');
        Route::post('/phase-mode/switch', [\App\Http\Controllers\Admin\PhaseModeController::class, 'switch'])->name('phase-mode.switch');
        Route::put('/phase-mode/{id}', [\App\Http\Controllers\Admin\PhaseModeController::class, 'update'])->name('phase-mode.update');
        
        // PHASE 2: Testing Mode Control
        Route::get('/testing-mode', [\App\Http\Controllers\Admin\TestingModeController::class, 'index'])->name('testing-mode.index');
        Route::post('/testing-mode/toggle', [\App\Http\Controllers\Admin\TestingModeController::class, 'toggle'])->name('testing-mode.toggle');
        Route::post('/testing-mode/users', [\App\Http\Controllers\Admin\TestingModeController::class, 'addUser'])->name('testing-mode.add-user');
        Route::delete('/testing-mode/users/{userId}', [\App\Http\Controllers\Admin\TestingModeController::class, 'removeUser'])->name('testing-mode.remove-user');
        Route::put('/testing-mode', [\App\Http\Controllers\Admin\TestingModeController::class, 'update'])->name('testing-mode.update');
        
        // ADMIN PANEL: Feature Flag Management
        Route::get('/feature-flags', [\App\Http\Controllers\Admin\FeatureFlagController::class, 'index'])->name('feature-flags.index');
        Route::put('/feature-flags/{id}', [\App\Http\Controllers\Admin\FeatureFlagController::class, 'update'])->name('feature-flags.update');
        Route::post('/feature-flags/{key}/toggle', [\App\Http\Controllers\Admin\FeatureFlagController::class, 'toggle'])->name('feature-flags.toggle');
        
        // ADMIN PANEL: Live & Call Management
        Route::get('/live-calls/stats', [\App\Http\Controllers\Admin\LiveCallController::class, 'stats'])->name('live-calls.stats');
        Route::post('/live-calls/broadcasts/{id}/force-end', [\App\Http\Controllers\Admin\LiveCallController::class, 'forceEndBroadcast'])->name('live-calls.broadcast.force-end');
        Route::post('/live-calls/calls/{id}/force-end', [\App\Http\Controllers\Admin\LiveCallController::class, 'forceEndCall'])->name('live-calls.call.force-end');
        
        // Admin Settings Pages
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::get('/system-settings', [AdminController::class, 'systemSettings'])->name('system-settings');
        
        // Bot Contacts Management
        Route::resource('bot-contacts', \App\Http\Controllers\Admin\BotContactController::class);
        Route::post('/bot-contacts/{botContact}/regenerate-code', [\App\Http\Controllers\Admin\BotContactController::class, 'regenerateCode'])->name('bot-contacts.regenerate-code');
        
        // Upload Settings Management
        Route::prefix('upload-settings')->name('upload-settings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\UploadSettingsController::class, 'index'])->name('index');
            Route::put('/global', [\App\Http\Controllers\Admin\UploadSettingsController::class, 'updateGlobalSettings'])->name('update-global');
            Route::get('/user-overrides', [\App\Http\Controllers\Admin\UploadSettingsController::class, 'getUserOverrides'])->name('user-overrides');
            Route::post('/user-overrides', [\App\Http\Controllers\Admin\UploadSettingsController::class, 'createUserOverride'])->name('create-override');
            Route::put('/user-overrides/{id}', [\App\Http\Controllers\Admin\UploadSettingsController::class, 'updateUserOverride'])->name('update-override');
            Route::delete('/user-overrides/{id}', [\App\Http\Controllers\Admin\UploadSettingsController::class, 'deleteUserOverride'])->name('delete-override');
            Route::get('/search-users', [\App\Http\Controllers\Admin\UploadSettingsController::class, 'searchUsers'])->name('search-users');
        });
        
        // System Logs Management
        Route::prefix('logs')->name('logs.')->group(function () {
            Route::get('/', [LogController::class, 'index'])->name('index');
            Route::post('/refresh', [LogController::class, 'refresh'])->name('refresh');
            Route::post('/clear', [LogController::class, 'clear'])->name('clear');
            Route::get('/download', [LogController::class, 'download'])->name('download');
            Route::post('/clear-all', [LogController::class, 'clearAll'])->name('clear-all');
            Route::get('/download-all', [LogController::class, 'downloadAll'])->name('download-all');
        });
    });
/*
|------------------
| Direct Chat Links (like wa.me)
|------------------
*/
Route::get('/me/{identifier}', [DirectChatController::class, 'handleDirectLink'])
    ->name('direct.chat');

/*
|------------------
| Group Invite Routes
|------------------
*/
Route::get('/groups/join/{inviteCode}', [GroupController::class, 'joinViaInvite'])
    ->name('groups.join-via-invite');

// -------------------
// Pin / Unpin a conversation via AJAX
// This route toggles a conversation's pinned status for the current user.
// Pinned conversations appear at the top of the sidebar. Limited to 5.
Route::post('/conversation/{conversation}/pin', [ChatController::class, 'pin'])
    ->name('conversation.pin')
    ->middleware('auth');

// Mark conversation as unread
Route::post('/conversation/{conversation}/mark-unread', [ChatController::class, 'markAsUnread'])
    ->name('conversation.mark-unread')
    ->middleware('auth');

// Clear the session flag that triggers the Google contacts modal. This prevents the modal from
// appearing repeatedly after the user has seen it once. Invoked via AJAX from the modal script.
Route::post('/clear-google-modal-flag', function () {
    session()->forget('show_google_contact_modal');
    return response()->json(['ok' => true]);
})->middleware('auth');

/*
|------------------
| Misc / Utility
|------------------
*/
Route::view('/offline', 'offline')->name('offline');
Route::view('/home', 'home')->name('homepage');
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('chat.index')
        : redirect()->route('login');
})->name('home');

// Google OAuth routes
Route::middleware(['auth'])->group(function () {
    Route::get('/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
    Route::post('/google/sync', [GoogleAuthController::class, 'sync'])->name('google.sync');
    Route::post('/google/disconnect', [GoogleAuthController::class, 'disconnect'])->name('google.disconnect');
    Route::get('/google/status', [GoogleAuthController::class, 'status'])->name('google.status');
});

// Legal pages
Route::get('/privacy-policy', function () {
    return view('pages.privacy-policy');
})->name('privacy.policy');

Route::get('/terms-of-service', function () {
    return view('pages.terms-of-service');
})->name('terms.service');
// Add this to your web.php routes file
Route::get('/clear-sw', function () {
    // Clear service worker cache
    try {
        // Delete service worker registration
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        
        return response()->json([
            'status' => 'success',
            'message' => 'Service worker cache cleared'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
})->name('clear.sw');

}); // End of chat subdomain group

/*
|--------------------------------------------------------------------------
| Web Routes (web.gekychat.com) - Main chat application
|--------------------------------------------------------------------------
| This is now the primary chat domain. Redirects to itself to maintain compatibility.
*/
Route::domain('web.gekychat.com')->group(function () {
    // Redirect all routes to web.gekychat.com (main chat domain)
    Route::get('/{any?}', function ($any = null) {
        $path = $any ? '/' . $any : '/';
        $queryString = request()->getQueryString();
        $fullPath = $path . ($queryString ? '?' . $queryString : '');
        // For now, redirect to chat.gekychat.com until routes are moved
        return redirect('https://chat.gekychat.com' . $fullPath, 301);
    })->where('any', '.*');
});

/*
|--------------------------------------------------------------------------
| Public Preview Routes (chat.gekychat.com) - No authentication required
|--------------------------------------------------------------------------
| These routes provide public previews of groups and user profiles
| They use the deep link handler to open in desktop app
| These routes are OUTSIDE the main chat domain group to avoid auth requirements
*/
// Public group preview on chat.gekychat.com
Route::domain('chat.gekychat.com')->get('/g/{slug}', [GroupController::class, 'publicPreview'])
    ->name('groups.public-preview');

// Public user profile preview on chat.gekychat.com
Route::domain('chat.gekychat.com')->get('/user/{user}/preview', [ContactsController::class, 'publicProfilePreview'])
    ->name('user.public-preview');

// Alternative route for user profiles on chat.gekychat.com
Route::domain('chat.gekychat.com')->get('/profile/{user}', [ContactsController::class, 'publicProfilePreview'])
    ->name('profile.public-preview');

// PHASE 2: Email webhook (public, no auth required)
Route::post('/webhook/email/incoming', [EmailWebhookController::class, 'incoming']);

// Health check (accessible from all domains)
Route::match(['GET', 'HEAD'], '/ping', fn() => response()->noContent())->name('ping');

// Deep link verification files (must be accessible from all domains)
Route::get('/.well-known/assetlinks.json', [\App\Http\Controllers\DeepLinkController::class, 'assetlinks'])
    ->name('deep-link.assetlinks');
Route::get('/.well-known/apple-app-site-association', [\App\Http\Controllers\DeepLinkController::class, 'appleAppSiteAssociation'])
    ->name('deep-link.apple-app-site-association');