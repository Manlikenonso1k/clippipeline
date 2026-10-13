<?php

use App\Jobs\QueueHeartbeatJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Worker liveness heartbeat — app:health reports it as down if this stops updating.
Schedule::job(new QueueHeartbeatJob)->everyMinute();

// Pull latest TikTok videos (skips cleanly when the scraper is not configured).
Schedule::command('tiktok:poll-latest')->everyFifteenMinutes()->withoutOverlapping();

// Shared-hosting scheduler (docs/CONTRACT.md §8). Agents edit only inside their own block.

// --- agent:publish ---
// No supervisor on Hostinger: drain the database queue once a minute.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();
Schedule::command('posts:dispatch-scheduled')->everyMinute()->withoutOverlapping();
// --- end agent:publish ---

// --- agent:connect ---
Schedule::command('accounts:refresh-tokens')->hourly()->withoutOverlapping();
// --- end agent:connect ---

// --- agent:analytics ---
Schedule::command('analytics:sync')->everyThreeHours()->withoutOverlapping();
Schedule::command('analytics:sync --recent')->everyTenMinutes()->withoutOverlapping();
// --- end agent:analytics ---
