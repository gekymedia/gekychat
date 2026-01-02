<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;

/*
|--------------------------------------------------------------------------
| Landing Page Routes (gekychat.com)
|--------------------------------------------------------------------------
|
| These routes are accessible only on the main domain (gekychat.com)
| without any subdomain prefix.
|
*/

Route::domain(config('app.landing_domain', 'gekychat.com'))->group(function () {
    
    // Landing page
    Route::get('/', [LandingController::class, 'index'])->name('landing.index');
    
    // Features page
    Route::get('/features', [LandingController::class, 'features'])->name('landing.features');
    
    // Pricing page
    Route::get('/pricing', [LandingController::class, 'pricing'])->name('landing.pricing');
    
    // Documentation
    Route::get('/docs', [LandingController::class, 'docs'])->name('landing.docs');
    
    // Redirect login to chat subdomain
    Route::get('/login', [LandingController::class, 'login'])->name('landing.login');
    
    // Legal pages (accessible from landing page)
    Route::get('/privacy-policy', function () {
        return view('pages.privacy-policy');
    })->name('landing.privacy.policy');
    
    Route::get('/terms-of-service', function () {
        return view('pages.terms-of-service');
    })->name('landing.terms.service');
    
    // Health check (works on all domains)
    Route::match(['GET', 'HEAD'], '/ping', fn() => response()->noContent())->name('landing.ping');
});

