<?php

namespace App\Services\Analytics;

use App\Exceptions\NeedsReauthException;
use App\Models\AccountFollowerSnapshot;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\TokenManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls lifetime counters for published distributions and follower counts per
 * account, writing one snapshot per distribution / account per UTC day
 * (re-running the same day overwrites that day's row). Never throws: failures
 * are logged and the affected account (or, on rate limits, platform) is skipped.
 */
class AnalyticsSync
{
    /** @var array<string, PlatformFetcher> */
    private array $fetchers = [];

    /** @var array<string, true> platforms stopped for this run after a rate limit */
    private array $stopped = [];

    public function __construct(
        private readonly TokenManager $tokens,
        YouTubeFetcher $youtube,
        FacebookFetcher $facebook,
        InstagramFetcher $instagram,
        TikTokFetcher $tiktok,
    ) {
        foreach ([$youtube, $facebook, $instagram, $tiktok] as $fetcher) {
            $this->fetchers[$fetcher->platform()] = $fetcher;
        }
    }

    /**
     * @param  bool  $recent  only distributions published 55–120 min ago without a post-publish snapshot
     * @return array{distributions:int, snapshots:int, accounts:int, followers:int, errors:int, rate_limited:array<int,string>}
     */
    public function run(?User $user = null, bool $recent = false): array
    {
        $this->stopped = [];
        $stats = ['distributions' => 0, 'snapshots' => 0, 'accounts' => 0, 'followers' => 0, 'errors' => 0];
        $today = now('UTC')->toDateString();

        $distributions = $this->distributionsQuery($user, $recent)->with('post:id,user_id')->get()
            ->filter(fn (PostDistribution $d) => $d->post && isset($this->fetchers[$d->platform]));
        $stats['distributions'] = $distributions->count();

        foreach ($distributions->groupBy(fn ($d) => $d->post->user_id.'|'.$d->platform) as $key => $group) {
            [$userId, $platform] = explode('|', (string) $key, 2);
            if (isset($this->stopped[$platform])) {
                continue;
            }

            $account = $this->accountFor((int) $userId, $platform);
            $token = $account ? $this->token($account) : null;
            if (! $token) {
                continue;
            }

            try {
                $metrics = $this->fetchers[$platform]->fetchMetrics($account, $token, $group->values()->all());
            } catch (RateLimitedException $e) {
                $this->stop($platform, $e);

                continue;
            } catch (Throwable $e) {
                $stats['errors']++;
                Log::warning('analytics.sync.metrics_failed', ['platform' => $platform, 'account' => $account->id, 'error' => $e->getMessage()]);

                continue;
            }

            foreach ($group as $distribution) {
                if (isset($metrics[$distribution->id])) {
                    $this->storeSnapshot($distribution, $metrics[$distribution->id], $today);
                    $stats['snapshots']++;
                }
            }
        }

        if (! $recent) {
            $accounts = SocialAccount::query()
                ->when($user, fn ($q) => $q->where('user_id', $user->id))
                ->whereIn('provider', array_keys($this->fetchers))
                ->get();

            foreach ($accounts as $account) {
                if (isset($this->stopped[$account->provider]) || $account->status === 'needs_reauth') {
                    continue;
                }
                $stats['accounts']++;
                $token = $this->token($account);
                if (! $token) {
                    continue;
                }

                try {
                    $followers = $this->fetchers[$account->provider]->fetchFollowers($account, $token);
                } catch (RateLimitedException $e) {
                    $this->stop($account->provider, $e);

                    continue;
                } catch (Throwable $e) {
                    $stats['errors']++;
                    Log::warning('analytics.sync.followers_failed', ['account' => $account->id, 'error' => $e->getMessage()]);

                    continue;
                }

                if ($followers !== null) {
                    $this->storeFollowers($account, $followers, $today);
                    $stats['followers']++;
                }
            }
        }

        $stats['rate_limited'] = array_keys($this->stopped);

        return $stats;
    }

    /** @return Builder<PostDistribution> */
    public function distributionsQuery(?User $user, bool $recent): Builder
    {
        $query = PostDistribution::query()
            ->where('status', PostDistribution::STATUS_PUBLISHED)
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->when($user, fn ($q) => $q->whereHas('post', fn ($p) => $p->where('user_id', $user->id)));

        if ($recent) {
            return $query
                ->whereBetween('published_at', [now()->subMinutes(120), now()->subMinutes(55)])
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('post_analytics')
                    ->whereColumn('post_analytics.distribution_id', 'post_distributions.id')
                    ->whereColumn('post_analytics.updated_at', '>=', 'post_distributions.published_at'));
        }

        $since = now()->subDays(max(1, (int) config('clippipeline.analytics.sync_window_days') ?: 90));

        return $query->where(fn ($q) => $q->where('published_at', '>=', $since)
            ->orWhere(fn ($q2) => $q2->whereNull('published_at')->where('updated_at', '>=', $since)));
    }

    private function accountFor(int $userId, string $platform): ?SocialAccount
    {
        $account = SocialAccount::query()->where('user_id', $userId)->where('provider', $platform)->first();

        return $account && $account->status !== 'needs_reauth' ? $account : null;
    }

    private function token(SocialAccount $account): ?string
    {
        try {
            return $this->tokens->freshAccessToken($account);
        } catch (NeedsReauthException $e) {
            Log::info('analytics.sync.needs_reauth', ['account' => $account->id, 'provider' => $account->provider]);
        } catch (Throwable $e) {
            Log::warning('analytics.sync.token_failed', ['account' => $account->id, 'error' => $e->getMessage()]);
        }

        return null;
    }

    private function stop(string $platform, Throwable $e): void
    {
        $this->stopped[$platform] = true;
        Log::warning('analytics.sync.rate_limited', ['platform' => $platform, 'error' => $e->getMessage()]);
    }

    /** @param  array<string,int>  $metrics */
    public function storeSnapshot(PostDistribution $distribution, array $metrics, string $day): PostAnalytic
    {
        return PostAnalytic::query()->updateOrCreate(
            ['distribution_id' => $distribution->id, 'captured_on' => $day],
            [
                'post_id' => $distribution->post_id,
                'platform' => $distribution->platform,
                'views' => $metrics['views'] ?? 0,
                'likes' => $metrics['likes'] ?? 0,
                'comments' => $metrics['comments'] ?? 0,
                'shares' => $metrics['shares'] ?? 0,
                'saves' => $metrics['saves'] ?? 0,
                'watch_time_seconds' => $metrics['watch_time_seconds'] ?? 0,
                'avg_view_duration_seconds' => $metrics['avg_view_duration_seconds'] ?? 0,
                // Legacy column (unsigned int) kept in step for older readers.
                'watch_time' => min(4294967295, (int) ($metrics['watch_time_seconds'] ?? 0)),
                'recorded_at' => now(),
            ],
        );
    }

    private function storeFollowers(SocialAccount $account, int $followers, string $day): void
    {
        AccountFollowerSnapshot::query()->updateOrCreate(
            ['social_account_id' => $account->id, 'captured_on' => $day],
            ['platform' => $account->provider, 'followers' => $followers],
        );

        SocialAccount::query()->whereKey($account->id)->update(['follower_count' => $followers]);
    }
}
