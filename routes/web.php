<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\IntegrationController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/billing/plan/{plan}', [BillingController::class, 'select'])->name('billing.select');
Route::post('/billing/checkout/{plan}/{gateway}', [BillingController::class, 'checkout'])->name('billing.checkout');
Route::get('/billing/callback/{gateway}', [BillingController::class, 'callback'])->name('billing.callback');
Route::post('/billing/webhook/{gateway}', [BillingController::class, 'webhook'])->name('billing.webhook');

// Authentication (Google) - main login
Route::get('/auth/redirect/{provider}', [IntegrationController::class, 'redirectToProvider'])->name('auth.redirect');
Route::get('/auth/callback/{provider}', [IntegrationController::class, 'handleProviderCallback'])->name('auth.callback');

// Integrations (connect third-party APIs)
Route::middleware(['auth'])->group(function () {
    Route::get('/integrations', function () { return view('integrations.index'); })->name('integrations.index');
    Route::get('/integrations/redirect/{provider}', [IntegrationController::class, 'redirectIntegration'])->name('integrations.redirect');
    Route::get('/integrations/callback/{provider}', [IntegrationController::class, 'handleIntegrationCallback'])->name('integrations.callback');
});
