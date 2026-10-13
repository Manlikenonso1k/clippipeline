<?php

namespace App\Console\Commands;

use App\Exceptions\NeedsReauthException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Analytics\FacebookFetcher;
use App\Services\Analytics\InstagramFetcher;
use App\Services\Analytics\PlatformFetcher;
use App\Services\Analytics\TikTokFetcher;
use App\Services\Analytics\YouTubeFetcher;
use App\Services\Connect\TokenManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Live check of a user's connected accounts: refresh each token through TokenManager,
 * then make one cheap profile call (follower count) through the analytics fetchers.
 * Writes nothing except what the token refresh itself persists.
 */
class Smoke extends Command
{
    protected $signature = 'app:smoke {email : The user whose connected accounts are checked}';

    protected $description = 'Refresh tokens and make one cheap API call per connected account (OK / NEEDS_REAUTH / ERROR).';

    public function handle(TokenManager $tokens): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No user with email '.$this->argument('email'));

            return self::FAILURE;
        }

        $order = array_flip(SocialAccount::PLATFORMS);
        $accounts = SocialAccount::where('user_id', $user->id)->get()
            ->sortBy(fn (SocialAccount $a) => $order[$a->provider] ?? 99)
            ->values();

        if ($accounts->isEmpty()) {
            $this->warn("{$user->email} has no connected accounts.");

            return self::SUCCESS;
        }

        $failures = 0;
        foreach ($accounts as $account) {
            [$status, $message] = $this->check($account, $tokens);
            if ($status !== 'OK') {
                $failures++;
            }
            $this->line(sprintf('%-10s %-13s %s', $account->provider, $status, $message));
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{0:string,1:string} */
    private function check(SocialAccount $account, TokenManager $tokens): array
    {
        $fetcher = $this->fetcher((string) $account->provider);
        if (! $fetcher) {
            return ['ERROR', "unsupported provider '{$account->provider}'"];
        }

        if ($account->status === SocialAccount::STATUS_ERROR) {
            return ['ERROR', (string) ($account->last_error ?: 'account is in error state')];
        }

        try {
            if ($account->status === SocialAccount::STATUS_NEEDS_REAUTH || empty($account->access_token)) {
                return ['NEEDS_REAUTH', (string) ($account->last_error ?: 'reconnect the account')];
            }

            $tokens->refresh($account);
            $token = $tokens->freshAccessToken($account);
            $followers = $fetcher->fetchFollowers($account, $token);
        } catch (NeedsReauthException $e) {
            return ['NEEDS_REAUTH', $e->getMessage()];
        } catch (Throwable $e) {
            return ['ERROR', $e->getMessage()];
        }

        $handle = $account->handle ?: ($account->display_name ?: '-');
        $expires = $account->expires_at ? $account->expires_at->toIso8601String() : 'no expiry';

        return ['OK', "{$handle} · followers=".($followers ?? 'n/a')." · token expires {$expires}"];
    }

    private function fetcher(string $provider): ?PlatformFetcher
    {
        return match ($provider) {
            'youtube' => app(YouTubeFetcher::class),
            'facebook' => app(FacebookFetcher::class),
            'instagram' => app(InstagramFetcher::class),
            'tiktok' => app(TikTokFetcher::class),
            default => null,
        };
    }
}
