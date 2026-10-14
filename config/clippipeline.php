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
    // --- end agent:backend ---

];
