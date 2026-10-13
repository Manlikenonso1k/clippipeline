<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Analytics\AnalyticsSync;
use Illuminate\Console\Command;

class SyncAnalytics extends Command
{
    protected $signature = 'analytics:sync
        {--recent : Only distributions published 55-120 minutes ago with no post-publish snapshot}
        {--user= : Limit to one user by email (QA)}';

    protected $description = 'Pull per-post and follower analytics from YouTube, Facebook, Instagram and TikTok';

    public function handle(AnalyticsSync $sync): int
    {
        $user = null;
        if ($email = $this->option('user')) {
            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                $this->error("No user with email {$email}.");

                return self::FAILURE;
            }
        }

        $stats = $sync->run($user, (bool) $this->option('recent'));

        $this->info(sprintf(
            'Distributions: %d, snapshots: %d, accounts: %d, follower snapshots: %d, errors: %d%s',
            $stats['distributions'],
            $stats['snapshots'],
            $stats['accounts'],
            $stats['followers'],
            $stats['errors'],
            $stats['rate_limited'] ? ', rate limited: '.implode(',', $stats['rate_limited']) : '',
        ));

        return self::SUCCESS;
    }
}
