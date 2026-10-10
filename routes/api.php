<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/google/token', [AuthController::class, 'googleToken']);
Route::post('/auth/facebook/token', [AuthController::class, 'facebookToken']);
Route::post('/auth/tiktok/token', [AuthController::class, 'tiktokToken']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/social-accounts', [AuthController::class, 'socialAccounts']);
});
