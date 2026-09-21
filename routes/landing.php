<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;

/*
|--------------------------------------------------------------------------
| Landing Page Routes (gekychat.com)
|--------------------------------------------------------------------------
*/

Route::domain(config('app.landing_domain', 'gekychat.com'))->group(function () {
    Route::get('/', [LandingController::class, 'index'])->name('landing.index');

    Route::get('/features', [LandingController::class, 'features'])->name('landing.features');
    Route::get('/about', [LandingController::class, 'about'])->name('landing.about');
    Route::get('/download', [LandingController::class, 'download'])->name('landing.download');

    // No public stubs — send empty product pages home until content exists.
    Route::redirect('/pricing', '/');
    Route::redirect('/docs', '/');

    Route::redirect('/support', '/help');
    Route::get('/help', [LandingController::class, 'help'])->name('landing.help');
    Route::get('/contact', [LandingController::class, 'contact'])->name('landing.contact');

    Route::get('/login', [LandingController::class, 'login'])->name('landing.login');

    Route::get('/privacy-policy', function () {
        return view('pages.privacy-policy');
    })->name('landing.privacy.policy');

    Route::get('/terms-of-service', function () {
        return view('pages.terms-of-service');
    })->name('landing.terms.service');

    Route::get('/request-account-deletion', function () {
        return view('pages.account-deletion');
    })->name('landing.request.account.deletion');

    Route::match(['GET', 'HEAD'], '/ping', fn () => response()->noContent())->name('landing.ping');
});
