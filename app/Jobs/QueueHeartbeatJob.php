<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Scheduled onto the queue every minute. When the worker processes it the cache
 * heartbeat updates — so a stale heartbeat means the worker is down (item 6).
 */
class QueueHeartbeatJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Cache::put('queue:heartbeat', now()->toDateTimeString(), now()->addMinutes(10));
    }
}
