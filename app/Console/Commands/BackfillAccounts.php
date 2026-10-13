<?php

namespace App\Console\Commands;

use App\Exceptions\NeedsReauthException;
use App\Models\SocialAccount;
use App\Services\Connect\ConnectException;
use App\Services\Connect\ConnectService;
use App\Services\Connect\Providers\MetaConnector;
use App\Services\Connect\TokenManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-off go-live backfill (supersedes app:backfill-ig-ids): fill external_id,
 * IG user id, Page id/token and profile fields for accounts connected before
 * the new connect flow. Legacy Instagram rows holding a Facebook *user* token
 * are converted into the facebook (Page) + instagram rows.
 */
class BackfillAccounts extends Command
{
    protected $signature = 'app:backfill-accounts {--user= : Only this user id} {--dry-run : Report without writing}';

    protected $description = 'Fill missing external ids / IG user ids / Page ids and profile data for connected accounts.';

    public function handle(ConnectService $connect, TokenManager $tokens): int
    {
        $query = SocialAccount::query()->with('user')->orderBy('id');
        if ($uid = $this->option('user')) {
            $query->where('user_id', $uid);
        }

        $dry = (bool) $this->option('dry-run');
        $metaDone = [];

        foreach ($query->get() as $account) {
            $label = "#{$account->id} user {$account->user_id} {$account->provider}";

            if (! $account->access_token) {
                $this->warn("{$label}: no token, skipping");

                continue;
            }

            if ($dry) {
                $this->line("{$label}: external_id=".($account->external_id ?? 'null').' status='.($account->status ?? 'null'));

                continue;
            }

            try {
                if ($account->provider === 'instagram' && empty($account->meta['page_id'])) {
                    $this->backfillLegacyInstagram($account, $label, $metaDone);

                    continue;
                }

                if (in_array($account->user_id, $metaDone, true) && $account->connectProvider() === 'meta') {
                    continue; // rebuilt together with its instagram row
                }

                $tokens->freshAccessToken($account);
                $connect->connector($account->connectProvider())->syncProfile($account);

                if (! $account->external_id && ! empty($account->meta['ig_user_id'])) {
                    $account->external_id = (string) $account->meta['ig_user_id'];
                }
                $account->save();

                $this->info("{$label}: ok (external_id={$account->external_id})");
            } catch (NeedsReauthException $e) {
                $this->warn("{$label}: needs_reauth — {$e->getMessage()}");
            } catch (Throwable $e) {
                $this->warn("{$label}: failed — {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    /** @param  array<int, int>  $metaDone */
    private function backfillLegacyInstagram(SocialAccount $account, string $label, array &$metaDone): void
    {
        $meta = (array) $account->meta;

        // Instagram-Login flow tokens are IG user tokens, not Facebook user tokens.
        if (($meta['login_flow'] ?? null) === 'instagram') {
            if (! $account->external_id && ! empty($meta['ig_user_id'])) {
                $account->forceFill(['external_id' => (string) $meta['ig_user_id']])->save();
            }
            $this->info("{$label}: instagram-login flow, external_id={$account->external_id}");

            return;
        }

        $metaConnector = app(MetaConnector::class);

        try {
            [$facebook, $instagram] = $metaConnector->storeFromUserToken($account->user, (string) $account->access_token);
            $metaDone[] = $account->user_id;
            $this->info("{$label}: rebuilt Meta rows (page {$facebook->external_id}, ig ".($instagram->external_id ?? 'none').')');
        } catch (ConnectException $e) {
            $account->forceFill([
                'status' => SocialAccount::STATUS_NEEDS_REAUTH,
                'last_error' => 'Reconnect Facebook & Instagram: '.$e->getMessage(),
                'needs_attention' => true,
            ])->save();
            $this->warn("{$label}: marked needs_reauth — {$e->getMessage()}");
        }
    }
}
