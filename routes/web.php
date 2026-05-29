<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IntegrationController;

Route::get('/', function () {
    return view('welcome');
});

// Authentication (Google) - main login
Route::get('/auth/redirect/{provider}', [IntegrationController::class, 'redirectToProvider'])->name('auth.redirect');
Route::get('/auth/callback/{provider}', [IntegrationController::class, 'handleProviderCallback'])->name('auth.callback');

// Integrations (connect third-party APIs)
Route::middleware(['auth'])->group(function () {
    Route::get('/integrations', function () { return view('integrations.index'); })->name('integrations.index');
    Route::get('/integrations/redirect/{provider}', [IntegrationController::class, 'redirectIntegration'])->name('integrations.redirect');
    Route::get('/integrations/callback/{provider}', [IntegrationController::class, 'handleIntegrationCallback'])->name('integrations.callback');
});
