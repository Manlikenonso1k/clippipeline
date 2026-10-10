<?php

namespace App\Services;

use App\Jobs\PublishToInstagramReelsJob;
use App\Jobs\PublishToYouTubeShortsJob;
use App\Models\Post;
use App\Models\PostDistribution;
use App\Models\SocialAccount;

/**
 * Orchestrates manual cross-posting: for each requested provider it upserts a
 * post_distributions row (status=queued) carrying the form options in meta, then
 * dispatches the matching publish job. Targets every connected provider (item 8);
 * providers without a publisher yet are reported with a reason.
 */
class CrossPostService
{
    /**
     * Provider registry. publishable=false providers are shown disabled in the UI
     * with `reason` and listed in the deploy report.
     *
     * @return array<string, array{label: string, job: ?class-string, publishable: bool, reason: ?string}>
     */
    public function registry(): array
    {
        return [
            'youtube' => [
                'label' => 'YouTube Shorts',
                'job' => PublishToYouTubeShortsJob::class,
                'publishable' => true,
                'reason' => null,
            ],
            'instagram' => [
                'label' => 'Instagram Reels',
                'job' => PublishToInstagramReelsJob::class,
                'publishable' => true,
                'reason' => null,
            ],
            'facebook' => [
                'label' => 'Facebook Reels',
                'job' => null,
                'publishable' => false,
                'reason' => 'Facebook Reels publishing needs a connected Facebook Page plus App Review for the Reels API — not enabled yet.',
            ],
            'tiktok' => [
                'label' => 'TikTok',
                'job' => null,
                'publishable' => false,
                'reason' => 'TikTok direct posting needs an audited Content Posting API app with the video.publish scope — not enabled yet.',
            ],
        ];
    }

    /**
     * Per-provider availability for a user's cross-post form.
     *
     * @return array<int, array{provider: string, label: string, connected: bool, publishable: bool, reason: ?string}>
     */
    public function optionsForUser(int $userId): array
    {
        $connected = SocialAccount::query()
            ->where('user_id', $userId)
            ->pluck('provider')
            ->all();

        $out = [];
        foreach ($this->registry() as $provider => $info) {
            $isConnected = in_array($provider, $connected, true);
            $reason = $info['reason'];
            if (! $isConnected && ! $reason) {
                $reason = 'Connect this account first.';
            }
            $out[] = [
                'provider' => $provider,
                'label' => $info['label'],
                'connected' => $isConnected,
                'publishable' => $info['publishable'] && $isConnected,
                'reason' => ($info['publishable'] && $isConnected) ? null : $reason,
            ];
        }

        return $out;
    }

    /**
     * Create distributions and dispatch jobs for the chosen providers.
     *
     * @param  array<int, string>  $providers
     * @param  array<string, mixed>  $options  title, caption/description, hashtags, privacy
     * @return array{dispatched: array<int,string>, skipped: array<int, array{provider:string, reason:string}>}
     */
    public function dispatch(Post $post, array $providers, array $options = []): array
    {
        $registry = $this->registry();
        $dispatched = [];
        $skipped = [];

        foreach ($providers as $provider) {
            $info = $registry[$provider] ?? null;

            if (! $info || ! $info['publishable'] || ! $info['job']) {
                $skipped[] = ['provider' => $provider, 'reason' => $info['reason'] ?? 'Unsupported provider.'];

                continue;
            }

            $account = SocialAccount::query()
                ->where('user_id', $post->user_id)
                ->where('provider', $provider)
                ->first();

            if (! $account) {
                $skipped[] = ['provider' => $provider, 'reason' => 'No '.$provider.' account connected.'];

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
                    'meta' => $options,
                ],
            );

            $info['job']::dispatch($distribution->id);
            $dispatched[] = $provider;
        }

        return ['dispatched' => $dispatched, 'skipped' => $skipped];
    }

    /** Dispatch to every connected provider for the post owner (used by the TikTok flow). */
    public function dispatchAllConnected(Post $post, array $options = []): array
    {
        $providers = SocialAccount::query()
            ->where('user_id', $post->user_id)
            ->pluck('provider')
            ->unique()
            ->all();

        return $this->dispatch($post, $providers, $options);
    }
}
