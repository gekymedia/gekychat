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
    | Chat (DMs)
    |----------
    */
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/',                 [ChatController::class, 'index'])->name('index');
        Route::get('/new',              [ChatController::class, 'new'])->name('new');
        Route::post('/start',           [ChatController::class, 'start'])->name('start');

        // Show a conversation
        Route::get('/{id}',             [ChatController::class, 'show'])->whereNumber('id')->name('show');

        // Send / clear / read
        Route::post('/send',            [ChatController::class, 'send'])->name('send');
        Route::post('/clear/{id}',      [ChatController::class, 'clear'])->whereNumber('id')->name('clear');

        // Prefer body-based read API; keep legacy temporarily
        Route::post('/read',            [ChatController::class, 'markAsRead'])->name('read');
        Route::post('/read/{id}',       [ChatController::class, 'markAsRead'])->whereNumber('id'); // legacy

        // Typing indicator
        Route::post('/typing',          [ChatController::class, 'typing'])->name('typing');

        // History endpoint for infinite scroll
        Route::get('/{conversation}/history', [ChatController::class, 'history'])
            ->whereNumber('conversation')->name('history');

        // Forward DM to mixed targets (convos/groups)
        Route::post('/forward/targets', [ChatController::class, 'forwardToTargets'])->name('forward.targets');
    });

    // DM reactions endpoint used by the frontend JS
    Route::post('/messages/react', [ChatController::class, 'addReaction'])->name('messages.react');

    // Delete a DM message (used by JS via /messages/{id})
    Route::delete('/messages/{id}', [ChatController::class, 'deleteMessage'])
        ->whereNumber('id')->name('message.delete');

    // Removed duplicate: Route::post('/messages/forward', ...)  // prefer chat.forward.targets

    /*
    |----------
    | Profile
    |----------
    */
    Route::get('/profile',  [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    /*
    |----------
    | Security / Settings
    |----------
    */
    Route::get('/settings/security',  [SecurityController::class, 'show'])->name('settings.security.show');
    Route::post('/settings/security', [SecurityController::class, 'update'])->name('settings.security.update');

    /*
    |----------
    | Groups
    |----------
    */
    Route::prefix('groups')->name('groups.')->group(function () {
        Route::get('/create',  [GroupController::class, 'create'])->name('create');
        Route::post('/',       [GroupController::class, 'store'])->name('store');
        Route::get('/{group}', [GroupController::class, 'show'])->whereNumber('group')->name('show');
        Route::get('/{group}/edit', [GroupController::class, 'edit'])->whereNumber('group')->name('edit');

        // Group history endpoint for infinite scroll
        Route::get('/{group}/history', [GroupController::class, 'history'])
            ->whereNumber('group')->name('messages.history');

        // Forward a group message to mixed targets (groups and/or DMs)
        Route::post('/forward/targets', [GroupController::class, 'forwardToTargets'])->name('forward.targets');

        // Update group
        Route::put('/{group}', [GroupController::class, 'updateGroup'])->whereNumber('group')->name('update');

        // Messages in group
        Route::post('/{group}/messages',                 [GroupController::class, 'sendMessage'])->whereNumber('group')->name('messages.store');
        Route::put('/{group}/messages/{message}',        [GroupController::class, 'editMessage'])->whereNumber('group')->whereNumber('message')->name('messages.update');
        Route::delete('/{group}/messages/{message}',     [GroupController::class, 'deleteMessage'])->whereNumber('group')->whereNumber('message')->name('messages.delete');

        // Reactions (group)
        Route::post('/{group}/messages/{message}/reactions', [GroupController::class, 'addReaction'])
            ->whereNumber('group')->whereNumber('message')->name('messages.reactions');

        // Member management
        Route::post('/{group}/members',          [GroupController::class, 'addMembers'])->whereNumber('group')->name('members.add');
        Route::delete('/{group}/members/{user}', [GroupController::class, 'removeMember'])->whereNumber('group')->whereNumber('user')->name('members.remove');
        Route::post('/{group}/leave',            [GroupController::class, 'leave'])->whereNumber('group')->name('leave');
        Route::post('/{group}/transfer',         [GroupController::class, 'transferOwnership'])->whereNumber('group')->name('transfer');

        // Typing indicator
        Route::post('/{group}/typing', [GroupController::class, 'typing'])->whereNumber('group')->name('typing');
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
|
| If your Echo client uses authEndpoint '/pusher/auth', keep this.
| Otherwise prefer the default '/broadcasting/auth' via Broadcast::routes(...)
*/
Route::post('/pusher/auth', [BroadcastController::class, 'authenticate'])
    ->middleware(['web','auth']);

/*
|------------------
| Misc / Utility
|------------------
*/
Route::view('/offline', 'offline')->name('offline');
Route::get('/', fn() => redirect()->route('chat.index'))->name('home');

// Health check (GET and HEAD supported, no auth)
Route::match(['GET', 'HEAD'], '/ping', fn () => response()->noContent())->name('ping');
