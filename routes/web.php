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
use App\Http\Controllers\BroadcastAuthController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\BlockController;
use App\Http\Controllers\Admin\ApiClientController;
// Quick Reply and Status Controllers (WEB features)
use App\Http\Controllers\QuickReplyController;
use App\Http\Controllers\StatusController;

Route::post('/broadcasting/auth', [BroadcastAuthController::class, 'authenticate'])
    ->middleware(['web', 'auth']);

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
| Web Routes
|--------------------------------------------------------------------------
*/

// Logout (web session)
Route::post('/logout', function (Request $request) {
    Auth::guard()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout')->middleware('auth');

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

    // Email 2FA flow
    Route::get('/verify-2fa',      [TwoFactorController::class, 'show'])->name('verify.2fa');
    Route::post('/verify-2fa',     [TwoFactorController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('/resend-2fa',     [TwoFactorController::class, 'resend'])->name('resend.2fa')->middleware('throttle:5,1');
});

/*
|--------------------
| Authenticated App
|--------------------
*/
Route::middleware('auth')->group(function () {

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
        Route::put('/', [SettingsController::class, 'update'])->name('update');
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
    });
});

// API Documentation
Route::get('/api/docs', function () {
    return view('api.docs');
})->name('api.docs');

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
    Route::post('/', [\App\Http\Controllers\LabelController::class, 'store'])->name('store');
    Route::delete('/{label}', [\App\Http\Controllers\LabelController::class, 'destroy'])->name('destroy');
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
Route::prefix('c')->name('chat.')->group(function () {
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
    Route::get('/invite/{invite_code}', [GroupController::class, 'join'])->name('join');
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

        // Reports Management
        Route::get('/reports', [AdminController::class, 'reportsIndex'])->name('reports.index');
        Route::put('/reports/{report}', [AdminController::class, 'reportsUpdate'])->name('reports.update');

        // Blocked Users Management
        Route::get('/blocks', [AdminController::class, 'blocksIndex'])->name('blocks.index');
        Route::post('/blocks/{user}', [AdminController::class, 'blocksBlock'])->name('blocks.block');
        Route::delete('/blocks/{user}', [AdminController::class, 'blocksUnblock'])->name('blocks.unblock');

        // API Clients Management
        Route::get('/api-clients', [AdminController::class, 'apiClientsIndex'])->name('api-clients.index');
        Route::put('/api-clients/{client}/status', [AdminController::class, 'apiClientsUpdateStatus'])->name('api-clients.status');

        // Settings
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');

        // Legacy routes (for backward compatibility)
        Route::get('/reports-legacy', [AdminController::class, 'reports'])->name('reports.legacy');
        Route::get('/banned-users', [AdminController::class, 'bannedUsers'])->name('banned');
        Route::get('/apiclients', [AdminController::class, 'apiClients'])->name('api_clients.legacy');
        
        // Content Moderation (stubs)
        Route::get('/messages', [AdminController::class, 'messages'])->name('messages');
        Route::delete('/messages/{id}', [AdminController::class, 'delete'])->whereNumber('id')->name('message.delete');
        Route::get('/conversations', [AdminController::class, 'conversations'])->name('conversations');
        Route::delete('/conversations/{id}', [AdminController::class, 'deleteConversation'])->whereNumber('id')->name('conversation.delete');
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
    ->name('groups.join-via-invite')
    ->middleware('auth');

// -------------------
// Pin / Unpin a conversation via AJAX
// This route toggles a conversation's pinned status for the current user.
// Pinned conversations appear at the top of the sidebar. Limited to 5.
Route::post('/conversation/{conversation}/pin', [ChatController::class, 'pin'])
    ->name('conversation.pin')
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

// Health check (GET and HEAD supported, no auth)
Route::match(['GET', 'HEAD'], '/ping', fn() => response()->noContent())->name('ping');

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