<?php

namespace App\Console\Commands;

use App\Exceptions\NeedsReauthException;
use App\Jobs\PurgeUserDataJob;
use App\Models\AccountFollowerSnapshot;
use App\Models\PostAnalytic;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Connect\DisconnectService;
use App\Services\Connect\TokenManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Daily retention (COMPLIANCE A-02..A-05):
 *  1. re-check authorization of every active account (a token refresh/validation
 *     counts) and stamp last_authorized_check_at; refreshed profile data replaces
 *     cached titles/thumbnails/channel names;
 *  2. purge authorized data of accounts still unauthorized after 30 days;
 *  3. prune snapshots older than 36 months;
 *  4. re-dispatch account deletions that did not finish (≤ 7 days guarantee).
 */
class AccountsRetention extends Command
{
    protected $signature = 'accounts:retention';

    protected $description = 'Re-check platform authorization, purge unauthorized data and prune old snapshots.';

    public function handle(TokenManager $tokens): int
    {
        $checked = $flagged = $purged = 0;

        SocialAccount::query()
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', SocialAccount::STATUS_NEEDS_REAUTH))
            ->where(fn ($q) => $q->whereNull('last_authorized_check_at')->orWhere('last_authorized_check_at', '<', now()->subHours(20)))
            ->orderBy('id')
            ->each(function (SocialAccount $account) use ($tokens, &$checked, &$flagged) {
                $account->refresh();
                if ($account->status === SocialAccount::STATUS_NEEDS_REAUTH || empty($account->access_token)) {
                    return;
                }

                try {
                    $tokens->refresh($account);
                    $connector = app(ConnectService::class)->connector($account->connectProvider());
                    try {
                        $connector->syncProfile($account);
                        $account->save();
                    } catch (Throwable) {
                        // profile refresh is best effort
                    }
                    $checked++;
                } catch (NeedsReauthException) {
                    $flagged++;
                } catch (Throwable $e) {
                    $this->warn("#{$account->id} {$account->provider}: transient failure ({$e->getMessage()})");
                }
            });

        $days = (int) config('clippipeline.retention.unauthorized_purge_days', 30);
        SocialAccount::query()
            ->where('status', SocialAccount::STATUS_NEEDS_REAUTH)
            ->whereNotNull('reauth_required_at')
            ->where('reauth_required_at', '<', now()->subDays($days))
            ->get()
            ->each(function (SocialAccount $account) use (&$purged) {
                DisconnectService::purgePlatformData($account->user_id, [$account->provider]);
                $account->delete(); // follower snapshots cascade
                $purged++;
            });

        $cutoff = now('UTC')->subMonths((int) config('clippipeline.retention.snapshot_months', 36))->toDateString();
        $pruned = PostAnalytic::query()->whereNotNull('captured_on')->where('captured_on', '<', $cutoff)->delete()
            + AccountFollowerSnapshot::query()->where('captured_on', '<', $cutoff)->delete();

        $pending = User::query()->whereNotNull('deletion_requested_at')->pluck('id');
        $pending->each(fn ($id) => PurgeUserDataJob::dispatch((int) $id));

        $this->info("Checked {$checked}, needs_reauth {$flagged}, purged {$purged} unauthorized accounts, pruned {$pruned} snapshots, re-queued {$pending->count()} deletions.");

        return self::SUCCESS;
    }
}
