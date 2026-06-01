<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\SocialAuthController;
use App\Filament\Pages\ConnectAccounts;

Route::get('/', function () {
    return view('home');
});

Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');

// Simple login entry so `auth` middleware can redirect unauthenticated users.
// Redirects to the Google OAuth flow used as the main login provider.
Route::get('/login', function () {
    return redirect()->route('auth.redirect', ['provider' => 'google']);
})->name('login');

Route::post('/billing/plan/{plan}', [BillingController::class, 'select'])->name('billing.select');
Route::post('/billing/checkout/{plan}/{gateway}', [BillingController::class, 'checkout'])->name('billing.checkout');
Route::get('/billing/callback/{gateway}', [BillingController::class, 'callback'])->name('billing.callback');
Route::post('/billing/webhook/{gateway}', [BillingController::class, 'webhook'])->name('billing.webhook');

// Authentication (Google) - main login
Route::get('/auth/redirect/{provider}', [SocialAuthController::class, 'redirectToProvider'])->name('auth.redirect');
Route::get('/auth/callback/{provider}', [SocialAuthController::class, 'handleProviderCallback'])->name('auth.callback');

// Convenience named routes used by the frontend CTA buttons.
Route::get('/auth/google', function () { return redirect()->route('auth.redirect', ['provider' => 'google']); })->name('auth.google');
Route::get('/auth/tiktok', function () { return redirect()->route('integrations.redirect', ['provider' => 'tiktok']); })->name('auth.tiktok');
Route::get('/auth/meta', function () { return redirect()->route('integrations.redirect', ['provider' => 'instagram']); })->name('auth.meta');
Route::get('/auth/youtube', function () { return redirect()->route('integrations.redirect', ['provider' => 'youtube']); })->name('auth.youtube');

// Integrations (connect third-party APIs)
Route::middleware(['auth'])->group(function () {
    Route::get('/integrations', function () { return redirect()->to(ConnectAccounts::getUrl()); })->name('integrations.index');
    Route::get('/integrations/redirect/{provider}', [SocialAuthController::class, 'redirectIntegration'])->name('integrations.redirect');
    Route::get('/integrations/callback/{provider}', [SocialAuthController::class, 'handleIntegrationCallback'])->name('integrations.callback');
    Route::post('/integrations/disconnect/{provider}', [SocialAuthController::class, 'disconnect'])->name('integrations.disconnect');
});

// Temporary debug routes (local testing only) - expose integration redirects without auth
if (app()->environment('local')) {
    Route::get('/debug/integrations/redirect/{provider}', [SocialAuthController::class, 'redirectIntegration']);
}
