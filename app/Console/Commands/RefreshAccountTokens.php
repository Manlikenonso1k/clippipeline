<?php

namespace App\Console\Commands;

use App\Exceptions\NeedsReauthException;
use App\Models\SocialAccount;
use App\Services\Connect\TokenManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Hourly (routes/console.php): refresh YouTube/TikTok tokens expiring within the
 * next run window and re-validate Meta Page tokens, so publish/analytics never
 * start with a dead token. Failures flag the account needs_reauth + push.
 */
class RefreshAccountTokens extends Command
{
    protected $signature = 'accounts:refresh-tokens
        {--account= : Only this social_accounts id}
        {--window=3900 : Refresh tokens expiring within this many seconds}';

    protected $description = 'Refresh expiring OAuth tokens and validate Meta Page tokens.';

    public function handle(TokenManager $tokens): int
    {
        $window = max(300, (int) $this->option('window'));

        $query = SocialAccount::query()
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', SocialAccount::STATUS_NEEDS_REAUTH))
            ->whereNotNull('access_token');

        if ($id = $this->option('account')) {
            $query->whereKey($id);
        }

        $refreshed = $flagged = $failed = 0;

        $query->orderBy('id')->each(function (SocialAccount $account) use ($tokens, $window, &$refreshed, &$flagged, &$failed) {
            // Meta rows share a token; a sibling may have just been flagged.
            $account->refresh();
            if ($account->status === SocialAccount::STATUS_NEEDS_REAUTH || ! $tokens->isDue($account, $window)) {
                return;
            }

            try {
                $tokens->refresh($account);
                $refreshed++;
            } catch (NeedsReauthException $e) {
                $flagged++;
                $this->warn("#{$account->id} {$account->provider}: needs_reauth ({$account->last_error})");
            } catch (Throwable $e) {
                $failed++;
                $this->warn("#{$account->id} {$account->provider}: transient failure, will retry ({$e->getMessage()})");
            }
        });

        $this->info("Refreshed {$refreshed}, needs_reauth {$flagged}, transient failures {$failed}.");

        return self::SUCCESS;
    }
}
