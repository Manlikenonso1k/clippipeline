<?php

namespace App\Console\Commands;

use App\Models\PostDistribution;
use App\Services\CrossPostService;
use Illuminate\Console\Command;

/** Dispatch queued distributions of scheduled posts once scheduled_at is due. */
class DispatchScheduledPosts extends Command
{
    protected $signature = 'posts:dispatch-scheduled';

    protected $description = 'Dispatch publish jobs for scheduled posts that are due';

    public function handle(CrossPostService $crossPost): int
    {
        $count = 0;

        PostDistribution::query()
            ->where('status', PostDistribution::STATUS_QUEUED)
            ->whereNull('dispatched_at')
            ->whereHas('post', fn ($q) => $q->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now()))
            ->orderBy('id')
            ->chunkById(100, function ($distributions) use ($crossPost, &$count) {
                foreach ($distributions as $distribution) {
                    $crossPost->queue($distribution);
                    $count++;
                }
            });

        $this->info("Dispatched {$count} scheduled distribution(s).");

        return self::SUCCESS;
    }
}
