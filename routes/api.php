<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthBrokerController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\ConnectController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\DistributionController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\SocialAccountController;
use App\Http\Controllers\Api\TikTokController;
use App\Studio\Http\Controllers\AssetController as StudioAssetController;
use App\Studio\Http\Controllers\FileController as StudioFileController;
use App\Studio\Http\Controllers\JobController as StudioJobController;
use App\Studio\Http\Controllers\ProjectController as StudioProjectController;
use App\Studio\Http\Controllers\RenderController as StudioRenderController;
use App\Studio\Http\Controllers\TemplateController as StudioTemplateController;
use App\Studio\Http\Controllers\UsageController as StudioUsageController;
use App\Studio\Http\Controllers\WorkerController as StudioWorkerController;
use App\Studio\Http\Middleware\EnsureStudioEnabled;
use App\Studio\Http\Middleware\EnsureStudioWorker;
use Illuminate\Support\Facades\Route;

// Contract: docs/CONTRACT.md. Agents edit only inside their own marked block.

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/google/token', [AuthController::class, 'googleToken']);
Route::post('/auth/facebook/token', [AuthController::class, 'facebookToken']);
Route::post('/auth/tiktok/token', [AuthController::class, 'tiktokToken']);

// Public: a device registers its push token even signed-out (attaches to the
// user automatically when a bearer token is sent).
Route::post('/device-tokens', [DeviceTokenController::class, 'store']);

// --- agent:backend (public) ---
// Mobile sign-in broker, Meta/TikTok compliance callbacks (docs/CONTRACT.md §9, §15).
Route::get('/auth/{provider}/start', [AuthBrokerController::class, 'start'])->whereIn('provider', ['google', 'facebook', 'tiktok']);
Route::post('/auth/exchange', [AuthBrokerController::class, 'exchange'])->middleware('throttle:20,1');
Route::post('/meta/data-deletion', [ComplianceController::class, 'metaDataDeletion']);
Route::post('/meta/deauthorize', [ComplianceController::class, 'metaDeauthorize']);
Route::post('/webhooks/tiktok', [ComplianceController::class, 'tiktokWebhook']);
// --- end agent:backend (public) ---

// --- agent:studio (public) ---
// Studio signed file downloads and the read-only template gallery (CONTRACT §17).
Route::middleware(EnsureStudioEnabled::class)->group(function () {
    Route::get('/studio/files/{kind}/{id}/{variant}', [StudioFileController::class, 'show'])
        ->whereIn('kind', ['asset', 'render'])->whereNumber('id')->middleware('signed')->name('studio.file');
    Route::get('/studio/templates', [StudioTemplateController::class, 'index']);
    Route::get('/studio/templates/{slug}', [StudioTemplateController::class, 'show']);
});
// --- end agent:studio (public) ---

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

    // --- agent:connect ---
    Route::get('/connect/{provider}/start', [ConnectController::class, 'start']);
    Route::get('/social-accounts', [SocialAccountController::class, 'index']);
    Route::delete('/social-accounts/{id}', [SocialAccountController::class, 'destroy']);
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

    // --- agent:backend ---
    // Me/preferences/account deletion, notifications, delete post, creator-info, export (CONTRACT §10–14).
    Route::patch('/me', [MeController::class, 'update']);
    Route::delete('/me', [MeController::class, 'destroy']);
    Route::get('/me/preferences', [MeController::class, 'preferences']);
    Route::patch('/me/preferences', [MeController::class, 'updatePreferences']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);
    Route::get('/tiktok/creator-info', [TikTokController::class, 'creatorInfo']);
    Route::get('/analytics/export', [AnalyticsController::class, 'export']);
    // --- end agent:backend ---
    // --- agent:studio ---
    // Studio (docs/STUDIO_SPEC.md, CONTRACT §17).
    Route::middleware(EnsureStudioEnabled::class)->prefix('studio')->group(function () {
        Route::get('/projects', [StudioProjectController::class, 'index']);
        Route::post('/projects', [StudioProjectController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/projects/{id}', [StudioProjectController::class, 'show']);
        Route::patch('/projects/{id}', [StudioProjectController::class, 'update']);
        Route::delete('/projects/{id}', [StudioProjectController::class, 'destroy']);
        Route::post('/projects/{id}/assets', [StudioAssetController::class, 'store'])->middleware('throttle:60,1');
        Route::post('/projects/{id}/auto-cut', [StudioProjectController::class, 'autoCut'])->middleware('throttle:20,1');
        Route::post('/projects/{id}/undo-auto-cut', [StudioProjectController::class, 'undoAutoCut']);
        Route::post('/projects/{id}/apply-reference', [StudioProjectController::class, 'applyReference']);
        Route::post('/projects/{id}/render', [StudioProjectController::class, 'render'])->middleware('throttle:20,1');
        Route::get('/projects/{id}/renders', [StudioProjectController::class, 'renders']);
        Route::get('/assets/{id}', [StudioAssetController::class, 'show']);
        Route::get('/assets/{id}/transcript', [StudioAssetController::class, 'transcript']);
        Route::delete('/assets/{id}', [StudioAssetController::class, 'destroy']);
        Route::get('/jobs/{id}', [StudioJobController::class, 'show']);
        Route::post('/jobs/{id}/cancel', [StudioJobController::class, 'cancel']);
        Route::post('/renders/{id}/cross-post', [StudioRenderController::class, 'crossPost'])->middleware('throttle:10,1');
        Route::delete('/renders/{id}', [StudioRenderController::class, 'destroy']);
        Route::post('/templates/{slug}/customize', [StudioTemplateController::class, 'customize'])->middleware('throttle:20,1');
        Route::get('/usage', [StudioUsageController::class, 'show']);
    });
    Route::middleware(EnsureStudioWorker::class)->prefix('worker')->group(function () {
        Route::post('/claim', [StudioWorkerController::class, 'claim']);
        Route::post('/jobs/{id}/heartbeat', [StudioWorkerController::class, 'heartbeat']);
        Route::post('/jobs/{id}/complete', [StudioWorkerController::class, 'complete']);
        Route::post('/jobs/{id}/fail', [StudioWorkerController::class, 'fail']);
    });
    // --- end agent:studio ---
});
