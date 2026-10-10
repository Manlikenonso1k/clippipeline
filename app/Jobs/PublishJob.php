<?php

namespace App\Jobs;

use App\Models\PostDistribution;
use App\Models\SocialAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base for all platform publish jobs.
 *
 * - user-scoped: looks the account up on the post OWNER, never globally (item 5)
 * - idempotent + ShouldBeUnique keyed on the distribution id: a retry or double
 *   dispatch will not post twice (item 12)
 * - records queued -> processing -> published/failed with the real error message
 */
abstract class PublishJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $distributionId) {}

    public function uniqueId(): string
    {
        return (string) $this->distributionId;
    }

    /** The social_accounts.provider this job publishes to. */
    abstract protected function provider(): string;

    /** Do the actual upload. Return the external id. Throw on failure. */
    abstract protected function publish(PostDistribution $distribution, SocialAccount $account): string;

    public function handle(): void
    {
        $distribution = PostDistribution::with('post')->find($this->distributionId);
        if (! $distribution) {
            return;
        }

        // Idempotency: never re-post something already published (item 12).
        if ($distribution->isPublished() || $distribution->external_id) {
            return;
        }

        $post = $distribution->post;
        if (! $post) {
            $this->fail($distribution, 'Post no longer exists.');

            return;
        }

        // User-scoped account lookup — the post owner's account only (item 5).
        $account = SocialAccount::query()
            ->where('user_id', $post->user_id)
            ->where('provider', $this->provider())
            ->first();

        if (! $account) {
            $this->fail($distribution, 'No '.$this->provider().' account connected for this user.');

            return;
        }

        $distribution->update(['status' => PostDistribution::STATUS_PROCESSING]);

        try {
            $externalId = $this->publish($distribution, $account);

            $distribution->update([
                'status' => PostDistribution::STATUS_PUBLISHED,
                'external_id' => $externalId,
                'published_at' => now(),
                'error' => null,
                'error_message' => null,
                'meta' => array_merge((array) $distribution->meta, ['published_via' => $this->provider()]),
            ]);
        } catch (Throwable $e) {
            $this->fail($distribution, $e->getMessage());
            throw $e; // let the queue retry/record in failed_jobs
        }
    }

    protected function fail(PostDistribution $distribution, string $message): void
    {
        Log::error("[{$this->provider()}] publish failed for distribution {$distribution->id}: {$message}");

        $distribution->update([
            'status' => PostDistribution::STATUS_FAILED,
            'error' => $message,
            'error_message' => $message,
        ]);
    }
}
