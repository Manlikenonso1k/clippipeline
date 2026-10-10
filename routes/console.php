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
