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

    // --- agent:studio ---
    // Studio (docs/STUDIO_SPEC.md, CONTRACT §17).
    'studio' => [
        'enabled' => filter_var(env('STUDIO_ENABLED', true), FILTER_VALIDATE_BOOL),
        // off | ytdlp. Platform links (TikTok, Instagram, YouTube, Facebook, X) are refused unless ytdlp.
        'link_fetcher' => env('STUDIO_LINK_FETCHER') ?: 'off',
        'ai_enabled' => filter_var(env('STUDIO_AI_ENABLED', false), FILTER_VALIDATE_BOOL),
        'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
        'ai_model' => env('STUDIO_AI_MODEL') ?: 'claude-haiku-5-5',
        'max_upload_mb' => (int) (env('STUDIO_MAX_UPLOAD_MB') ?: 500),
        'max_source_minutes' => (int) (env('STUDIO_MAX_SOURCE_MINUTES') ?: 10),
        'lease_minutes' => 10,
        'signed_url_minutes' => 120,
        'scene_threshold' => 0.3,
        // Keyed by the contract plan name (MeService::plan()['name']). Placeholders until Nonso confirms.
        'plans' => [
            'free' => ['studio_render_minutes' => 10, 'studio_ai_calls' => 20, 'studio_max_source_minutes' => 5],
            'pro' => ['studio_render_minutes' => 120, 'studio_ai_calls' => 300, 'studio_max_source_minutes' => 10],
            'agency' => ['studio_render_minutes' => 600, 'studio_ai_calls' => 2000, 'studio_max_source_minutes' => 30],
        ],
        'retention' => [
            'preview_render_days' => 14,
            'proxy_idle_days' => 30,
        ],
        'default_fillers' => ['um', 'uh', 'er', 'ehm', 'hmm'],
        // YouTube Data API key (an API key, not OAuth) for studio:trends-refresh.
        'youtube_api_key' => env('STUDIO_YOUTUBE_API_KEY'),
    ],
    // --- end agent:studio ---

];
