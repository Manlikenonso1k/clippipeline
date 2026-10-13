<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConnectController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\DistributionController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\SocialAccountController;
use Illuminate\Support\Facades\Route;

// Contract: docs/CONTRACT.md. Agents edit only inside their own marked block.

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/google/token', [AuthController::class, 'googleToken']);
Route::post('/auth/facebook/token', [AuthController::class, 'facebookToken']);
Route::post('/auth/tiktok/token', [AuthController::class, 'tiktokToken']);

// Public: a device registers its push token even signed-out (attaches to the
// user automatically when a bearer token is sent).
Route::post('/device-tokens', [DeviceTokenController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

    // --- agent:connect ---
    Route::get('/connect/{provider}/start', [ConnectController::class, 'start']);
    Route::get('/social-accounts', [SocialAccountController::class, 'index']);
    Route::delete('/social-accounts/{socialAccount}', [SocialAccountController::class, 'destroy']);
    // --- end agent:connect ---

    // --- agent:publish ---
    Route::get('/posts', [PostController::class, 'index']);
    Route::post('/posts', [PostController::class, 'store']);
    Route::get('/posts/{post}', [PostController::class, 'show']);
    Route::post('/distributions/{distribution}/retry', [DistributionController::class, 'retry']);
    // --- end agent:publish ---

    // --- agent:analytics ---
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/analytics/platforms', [AnalyticsController::class, 'platforms']);
    Route::get('/analytics/timeseries', [AnalyticsController::class, 'timeseries']);
    Route::get('/analytics/top-posts', [AnalyticsController::class, 'topPosts']);
    Route::get('/posts/{post}/analytics', [AnalyticsController::class, 'post']);
    // --- end agent:analytics ---
});
