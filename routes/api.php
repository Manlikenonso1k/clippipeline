<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\PostController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/google/token', [AuthController::class, 'googleToken']);
Route::post('/auth/facebook/token', [AuthController::class, 'facebookToken']);
Route::post('/auth/tiktok/token', [AuthController::class, 'tiktokToken']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/social-accounts', [AuthController::class, 'socialAccounts']);
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{post}', [PostController::class, 'show']);
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);
});
