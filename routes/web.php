<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Broadcasting\BroadcastController;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\PhoneVerificationController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\DirectChatController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ContactsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Public, guest-only auth endpoints (OTP + 2FA), then authenticated app,
| admin, and misc utility routes.
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
    | Chat (DMs) - Shortened to /c
    |----------
    */
    Route::prefix('c')->name('chat.')->group(function () {
        Route::get('/',                 [ChatController::class, 'index'])->name('index');
        Route::get('/new',              [ChatController::class, 'new'])->name('new');
        Route::post('/start',           [ChatController::class, 'start'])->name('start');

        // Show a conversation - now using slugs
        Route::get('/{conversation}',   [ChatController::class, 'show'])->name('show');

        // Send / clear / read
        Route::post('/send',            [ChatController::class, 'send'])->name('send');
        Route::post('/clear/{conversation}', [ChatController::class, 'clear'])->name('clear');

        // Prefer body-based read API; keep legacy temporarily
        Route::post('/read',            [ChatController::class, 'markAsRead'])->name('read');
        Route::post('/read/{conversation}', [ChatController::class, 'markAsRead']); // legacy

        // Typing indicator
        Route::post('/typing',          [ChatController::class, 'typing'])->name('typing');

        // History endpoint for infinite scroll
        Route::get('/{conversation}/history', [ChatController::class, 'history'])->name('history');

        // Forward DM to mixed targets (convos/groups)
        Route::post('/forward/targets', [ChatController::class, 'forwardToTargets'])->name('forward.targets');
    });

    // DM reactions endpoint used by the frontend JS
    Route::post('/messages/react', [ChatController::class, 'addReaction'])->name('messages.react');

    // Delete a DM message (used by JS via /messages/{id})
    Route::delete('/messages/{id}', [ChatController::class, 'deleteMessage'])
        ->whereNumber('id')->name('message.delete');
    // Add this to your routes - right after the delete message route
    Route::put('/messages/{id}', [ChatController::class, 'editMessage'])
        ->whereNumber('id')->name('message.edit');
    /*
    |----------
    | Contacts Management
    |----------
    */
    Route::prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', [ContactsController::class, 'index'])->name('index');
        Route::post('/sync', [ContactsController::class, 'sync'])->name('sync');
        Route::post('/resolve', [ContactsController::class, 'resolve'])->name('resolve');
        Route::post('/', [ContactsController::class, 'store'])->name('store');
        Route::get('/{contact}', [ContactsController::class, 'show'])->name('show');
        Route::put('/{contact}', [ContactsController::class, 'update'])->name('update');
        Route::delete('/{contact}', [ContactsController::class, 'destroy'])->name('destroy');
        Route::post('/{contact}/favorite', [ContactsController::class, 'favorite'])->name('favorite');
        Route::delete('/{contact}/favorite', [ContactsController::class, 'unfavorite'])->name('unfavorite');
    });

    /*
    |----------
    | Enhanced Search Routes
    |----------
    */
    Route::prefix('search')->name('search.')->group(function () {
        // Main search endpoint with all features
        Route::get('/', [SearchController::class, 'index'])->name('index');

        // Available search filters
        Route::get('/filters', [SearchController::class, 'searchFilters'])->name('filters');

        // Legacy search for backward compatibility
        Route::get('/legacy', [SearchController::class, 'legacySearch'])->name('legacy');

        // Filter-specific searches
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
    // Add this route INSIDE the groups prefix
    Route::get('/{group}/messages/partial', [GroupController::class, 'messagesPartial'])
        ->name('messages.partial')
        ->middleware('auth');
    // Group creation
    Route::get('/create',  [GroupController::class, 'create'])->name('create');
    Route::post('/',       [GroupController::class, 'store'])->name('store');

    // Invite routes
    Route::get('/invite/{invite_code}', [GroupController::class, 'join'])->name('join');

    // Forwarding
    Route::post('/forward/targets', [GroupController::class, 'forwardToTargets'])->name('forward.targets');

    // Group viewing and editing
    Route::get('/{group}', [GroupController::class, 'show'])->name('show');
    Route::get('/{group}/edit', [GroupController::class, 'edit'])->name('edit');
    Route::put('/{group}', [GroupController::class, 'updateGroup'])->name('update');

    // Group history
    Route::get('/{group}/history', [GroupController::class, 'history'])->name('messages.history');

    // Message management
    Route::post('/{group}/messages',                 [GroupController::class, 'sendMessage'])->name('messages.store');
    Route::put('/{group}/messages/{message}',        [GroupController::class, 'editMessage'])->name('messages.update');
    Route::delete('/{group}/messages/{message}',     [GroupController::class, 'deleteMessage'])->name('messages.delete');

    // Message reactions
    Route::post('/{group}/messages/{message}/reactions', [GroupController::class, 'addReaction'])->name('messages.reactions');

    // Member management
    Route::post('/{group}/members',          [GroupController::class, 'addMembers'])->name('members.add');
    Route::post('/{group}/members/{userId}/promote', [GroupController::class, 'promoteMember'])->name('members.promote');
    Route::delete('/{group}/members/{userId}', [GroupController::class, 'removeMember'])->name('members.remove');

    // Group actions
    Route::post('/{group}/leave',            [GroupController::class, 'leave'])->name('leave');
    Route::post('/{group}/transfer',         [GroupController::class, 'transferOwnership'])->name('transfer');

    // Typing indicator
    Route::post('/{group}/typing', [GroupController::class, 'typing'])->name('typing');

    // Invite management
    Route::post('/{group}/invite', [GroupController::class, 'generateInvite'])->name('invite.generate');
});
});

/*
|-----------
| Admin
|-----------
*/
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/messages',               [AdminController::class, 'messages'])->name('messages');
        Route::delete('/messages/{id}',       [AdminController::class, 'delete'])->whereNumber('id')->name('message.delete');
        Route::get('/conversations',          [AdminController::class, 'conversations'])->name('conversations');
        Route::delete('/conversations/{id}',  [AdminController::class, 'deleteConversation'])->whereNumber('id')->name('conversation.delete');
    });

/*
|------------------
| Broadcasting auth
|------------------
*/
Route::post('/pusher/auth', [BroadcastController::class, 'authenticate'])
    ->middleware(['web', 'auth']);

/*
|------------------
| Direct Chat Links (like wa.me)
|------------------
*/
Route::get('/me/{identifier}', [DirectChatController::class, 'handleDirectLink'])
    ->name('direct.chat');

/*
|------------------
| API Routes (for AJAX calls)
|------------------
*/
Route::prefix('api')->middleware('auth')->group(function () {
    // Enhanced search API endpoints
    Route::get('/search', [SearchController::class, 'index']);
    Route::get('/search/filters', [SearchController::class, 'searchFilters']);
    Route::get('/search/legacy', [SearchController::class, 'legacySearch']);
    Route::get('/search/contacts', [SearchController::class, 'searchContacts']);
    Route::get('/search/messages', [SearchController::class, 'searchMessages']);

    // Start chat with phone number
    Route::post('/start-chat-with-phone', [SearchController::class, 'startChatWithPhone']);

    // Legacy chat endpoints (keep for backward compatibility)
    Route::post('/chat/start-with-phone', [ChatController::class, 'startChatWithPhone'])
        ->name('api.chat.start-with-phone');

    // Contacts API endpoints
    Route::prefix('contacts')->group(function () {
        Route::get('/', [ContactsController::class, 'index']);
        Route::post('/sync', [ContactsController::class, 'sync']);
        Route::post('/resolve', [ContactsController::class, 'resolve']);
        Route::post('/', [ContactsController::class, 'store']);
        Route::get('/{contact}', [ContactsController::class, 'show']);
        Route::put('/{contact}', [ContactsController::class, 'update']);
        Route::delete('/{contact}', [ContactsController::class, 'destroy']);
        Route::post('/{contact}/favorite', [ContactsController::class, 'favorite']);
        Route::delete('/{contact}/favorite', [ContactsController::class, 'unfavorite']);
    });
});

/*
|------------------
| Misc / Utility
|------------------
*/
Route::view('/offline', 'offline')->name('offline');
Route::get('/', fn() => redirect()->route('chat.index'))->name('home');

// Health check (GET and HEAD supported, no auth)
Route::match(['GET', 'HEAD'], '/ping', fn() => response()->noContent())->name('ping');
// routes/web.php
Route::get('/test-broadcasting', function () {
    if (!auth()->check()) {
        return response()->json(['error' => 'Not authenticated'], 401);
    }
    
    return response()->json([
        'user' => auth()->user(),
        'session_id' => session()->getId(),
        'authenticated' => auth()->check()
    ]);
});