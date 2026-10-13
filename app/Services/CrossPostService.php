<?php

namespace App\Services;

use App\Jobs\PublishJob;
use App\Jobs\PublishToFacebookReelsJob;
use App\Jobs\PublishToInstagramReelsJob;
use App\Jobs\PublishToTikTokJob;
use App\Jobs\PublishToYouTubeShortsJob;
use App\Models\Post;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Orchestrates cross-posting: one post_distributions row per platform and one
 * queued publish job per row. Also the single retry path (API + Filament).
 */
class CrossPostService
{
    public const PLATFORMS = ['youtube', 'facebook', 'instagram', 'tiktok'];

    /**
     * @return array<string, array{label: string, job: class-string<PublishJob>, publishable: bool, reason: ?string}>
     */
    public function registry(): array
    {
        return [
            'youtube' => ['label' => 'YouTube Shorts', 'job' => PublishToYouTubeShortsJob::class, 'publishable' => true, 'reason' => null],
            'facebook' => ['label' => 'Facebook Reels', 'job' => PublishToFacebookReelsJob::class, 'publishable' => true, 'reason' => null],
            'instagram' => ['label' => 'Instagram Reels', 'job' => PublishToInstagramReelsJob::class, 'publishable' => true, 'reason' => null],
            'tiktok' => ['label' => 'TikTok', 'job' => PublishToTikTokJob::class, 'publishable' => true, 'reason' => null],
        ];
    }

    /** @return class-string<PublishJob>|null */
    public function jobFor(string $platform): ?string
    {
        return $this->registry()[$platform]['job'] ?? null;
    }

    /**
     * Per-provider availability for a user's cross-post form.
     *
     * @return array<int, array{provider: string, label: string, connected: bool, publishable: bool, reason: ?string}>
     */
    public function optionsForUser(int $userId): array
    {
        $connected = $this->activeAccounts($userId)->pluck('provider')->all();

        $out = [];
        foreach ($this->registry() as $provider => $info) {
            $isConnected = in_array($provider, $connected, true);
            $out[] = [
                'provider' => $provider,
                'label' => $info['label'],
                'connected' => $isConnected,
                'publishable' => $info['publishable'] && $isConnected,
                'reason' => $isConnected ? $info['reason'] : 'Connect this account first.',
            ];
        }

        return $out;
    }

    /**
     * Create distributions and dispatch jobs for the chosen providers.
     *
     * @param  array<int, string>  $providers
     * @param  array<string, mixed>  $options  title, caption/description, hashtags
     * @return array{dispatched: array<int,string>, skipped: array<int, array{provider:string, reason:string}>}
     */
    public function dispatch(Post $post, array $providers, array $options = []): array
    {
        $dispatched = [];
        $skipped = [];
        $connected = $this->activeAccounts($post->user_id)->pluck('provider')->all();

        foreach (array_unique($providers) as $provider) {
            if (! $this->jobFor($provider)) {
                $skipped[] = ['provider' => $provider, 'reason' => 'Unsupported provider.'];

                continue;
            }

            if (! in_array($provider, $connected, true)) {
                $skipped[] = ['provider' => $provider, 'reason' => 'No '.$provider.' account connected.'];

                continue;
            }

            $existing = PostDistribution::where('post_id', $post->id)->where('platform', $provider)->first();
            if ($existing && ($existing->isPublished() || $existing->isProcessing())) {
                $skipped[] = ['provider' => $provider, 'reason' => 'Already '.$existing->status.'.'];

                continue;
            }

            $distribution = PostDistribution::updateOrCreate(
                ['post_id' => $post->id, 'platform' => $provider],
                [
                    'status' => PostDistribution::STATUS_QUEUED,
                    'error' => null,
                    'error_message' => null,
                    'external_id' => null,
                    'published_at' => null,
                    'dispatched_at' => null,
                    'meta' => $options,
                ],
            );

            $this->queue($distribution);
            $dispatched[] = $provider;
        }

        return ['dispatched' => $dispatched, 'skipped' => $skipped];
    }

    /** Dispatch to every connected provider for the post owner (TikTok ingest flow; never back to TikTok). */
    public function dispatchAllConnected(Post $post, array $options = []): array
    {
        $providers = $this->activeAccounts($post->user_id)
            ->pluck('provider')
            ->reject(fn ($p) => $p === 'tiktok' && $post->source !== Post::SOURCE_UPLOAD)
            ->unique()
            ->values()
            ->all();

        return $this->dispatch($post, $providers, $options);
    }

    /** Push the platform job for a queued distribution and stamp dispatched_at. */
    public function queue(PostDistribution $distribution): void
    {
        $job = $this->jobFor((string) $distribution->platform);
        if (! $job) {
            return;
        }

        $distribution->forceFill(['dispatched_at' => now()])->save();
        $job::dispatch($distribution->id);
    }

    /**
     * Retry a failed distribution (API + Filament share this).
     *
     * @throws ConflictHttpException unless the distribution is failed
     */
    public function retry(PostDistribution $distribution): PostDistribution
    {
        if (! $distribution->isFailed()) {
            throw new ConflictHttpException('Only failed distributions can be retried.');
        }

        $meta = (array) $distribution->meta;
        foreach (PublishJob::TRANSIENT_META as $key) {
            unset($meta[$key]);
        }

        $distribution->forceFill([
            'status' => PostDistribution::STATUS_QUEUED,
            'error' => null,
            'error_message' => null,
            'external_id' => null,
            'meta' => $meta,
        ])->save();

        $this->queue($distribution);

        return $distribution->refresh();
    }

    /** Active accounts of a user (status defaults to active). */
    private function activeAccounts(int $userId)
    {
        return SocialAccount::query()
            ->where('user_id', $userId)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'active'))
            ->get(['id', 'provider']);
    }
}
