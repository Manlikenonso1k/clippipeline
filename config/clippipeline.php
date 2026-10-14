<?php

// App-level settings for ClipPipeline (docs/CONTRACT.md). Agents edit only inside their own block.

return [

    // --- agent:publish ---
    // Keep at or below the PHP upload_max_filesize / post_max_size set in hPanel.
    'max_upload_mb' => (int) (env('CLIPPIPELINE_MAX_UPLOAD_MB') ?: 200),
    // --- end agent:publish ---

    // --- agent:analytics ---
    'analytics' => [
        // Days of history the sync keeps refreshing for each published distribution.
        'sync_window_days' => (int) (env('ANALYTICS_SYNC_WINDOW_DAYS') ?: 90),
    ],
    // --- end agent:analytics ---

    // --- agent:backend ---
    // Platform audit flags drive the "stays private until review" notices (docs/COMPLIANCE.md).
    'tiktok_audited' => filter_var(env('TIKTOK_AUDITED', false), FILTER_VALIDATE_BOOL),
    'youtube_audited' => filter_var(env('YOUTUBE_AUDITED', false), FILTER_VALIDATE_BOOL),
    'support_email' => env('SUPPORT_EMAIL') ?: 'support@nonsostudio.com',

    // Me.plan (CONTRACT §10): billing plan key => contract plan name + monthly clip limit (null = unlimited).
    'plans' => [
        'starter' => ['name' => 'free', 'clips_limit' => 10],
        'creator_pro' => ['name' => 'pro', 'clips_limit' => 20],
        'lifetime' => ['name' => 'agency', 'clips_limit' => null],
    ],
    'billing_url' => env('BILLING_URL'),

    // Mobile sign-in broker (CONTRACT §9).
    'mobile_auth_redirect' => env('MOBILE_AUTH_REDIRECT') ?: 'clippipelinemobile://auth',
    'login_code_ttl_seconds' => 300,

    // Retention (COMPLIANCE A-02..A-04).
    'retention' => [
        'deletion_days' => 7,
        'unauthorized_purge_days' => 30,
        'snapshot_months' => 36,
    ],

    // Publishing caps (COMPLIANCE P-02/P-03).
    'caps' => [
        'facebook_reels_per_day' => 30,
        'instagram_posts_per_day' => 100,
    ],
    // --- end agent:backend ---

];
