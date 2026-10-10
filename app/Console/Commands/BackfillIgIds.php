<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Services\InstagramService;
use Illuminate\Console\Command;

/**
 * Fill ig_user_id for already-connected Instagram accounts (item 11).
 */
class BackfillIgIds extends Command
{
    protected $signature = 'app:backfill-ig-ids';

    protected $description = 'Resolve and store ig_user_id for connected Instagram accounts that are missing it.';

    public function handle(InstagramService $ig): int
    {
        $accounts = SocialAccount::where('provider', 'instagram')->get();
        $this->info("Checking {$accounts->count()} Instagram account(s)...");

        foreach ($accounts as $account) {
            $meta = (array) $account->meta;
            if (! empty($meta['ig_user_id'])) {
                $this->line("  user {$account->user_id}: already has ig_user_id");

                continue;
            }

            if (! $account->access_token) {
                $this->warn("  user {$account->user_id}: no access token, skipping");

                continue;
            }

            $resolved = $ig->resolveBusinessUserId($account->access_token);

            if (! $resolved) {
                $account->forceFill(['needs_attention' => true])->save();
                $this->warn("  user {$account->user_id}: could not resolve ig_user_id — marked needs_attention");

                continue;
            }

            $meta['ig_user_id'] = $resolved['ig_user_id'];
            $meta['login_flow'] = $resolved['flow'];
            $account->forceFill(['meta' => $meta, 'needs_attention' => false])->save();
            $this->info("  user {$account->user_id}: set ig_user_id={$resolved['ig_user_id']} ({$resolved['flow']})");
        }

        return self::SUCCESS;
    }
}
