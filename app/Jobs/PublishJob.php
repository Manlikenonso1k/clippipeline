<?php

namespace App\Jobs;

use App\Exceptions\NeedsReauthException;
use App\Models\Post;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Services\Connect\TokenManager;
use App\Services\PublishException;
use App\Support\VideoResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Base for all platform publish jobs.
 *
 * - Account lookup is scoped to the post OWNER and the platform; tokens always
 *   come from TokenManager (NeedsReauthException fails without retry).
 * - Idempotent (skips once external_id is set) and ShouldBeUnique per distribution.
 * - Status flow: queued -> uploading -> processing -> published | failed, with the
 *   platform's real error text and an attempts counter.
 * - Platform processing is polled with release($delay) rather than long sleeps;
 *   poll releases count toward $tries, real failures are capped by $maxExceptions.
 */
abstract class PublishJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Upper bound on runs including poll releases. */
    public int $tries = 30;

    /** Real failures before the job gives up ("3 tries"). */
    public int $maxExceptions = 3;

    /** Must stay below the database queue's retry_after (600). */
    public int $timeout = 540;

    /** Unique lock lifetime, so a crashed worker cannot block retries forever. */
    public int $uniqueFor = 3600;

    /** Polls before giving up on platform-side processing. */
    protected int $maxPolls = 20;

    /** Meta keys holding in-flight platform state (cleared on publish/retry). */
    public const TRANSIENT_META = ['ig_container_id', 'fb_video_id', 'fb_uploaded', 'fb_finished', 'tiktok_publish_id', 'yt_upload_uri', 'polls', 'awaiting_poll'];

    public function __construct(public int $distributionId) {}

    public function uniqueId(): string
    {
        return (string) $this->distributionId;
    }

    /** @return array<int, int> seconds between real-failure retries */
    public function backoff(): array
    {
        return [60, 300];
    }

    /** The platform (and social_accounts.provider) this job publishes to. */
    abstract protected function platform(): string;

    /**
     * Do (or continue) the upload. Return the result, or null when the job was
     * released to poll the platform again later.
     */
    abstract protected function publish(PostDistribution $distribution, SocialAccount $account, string $token): ?PublishResult;

    public function handle(): void
    {
        $distribution = PostDistribution::with('post')->find($this->distributionId);
        if (! $distribution) {
            return;
        }

        // Idempotency: never re-post something already published, never resurrect a failure.
        if ($distribution->external_id || $distribution->isPublished() || $distribution->isFailed()) {
            return;
        }

        $post = $distribution->post;
        if (! $post) {
            $this->markFailed($distribution, 'Post no longer exists.');

            return;
        }

        $account = SocialAccount::query()
            ->where('user_id', $post->user_id)
            ->where('provider', $this->platform())
            ->first();

        if (! $account) {
            $this->markFailed($distribution, 'No '.$this->platform().' account connected for this user.');

            return;
        }

        $meta = (array) $distribution->meta;
        if (empty($meta['awaiting_poll'])) {
            $distribution->attempts = (int) $distribution->attempts + 1;
        }
        unset($meta['awaiting_poll']);
        $distribution->meta = $meta;
        if ($distribution->status === PostDistribution::STATUS_QUEUED) {
            $distribution->status = PostDistribution::STATUS_UPLOADING;
        }
        $distribution->save();

        try {
            $token = app(TokenManager::class)->freshAccessToken($account);
            $result = $this->publish($distribution, $account, $token);
        } catch (NeedsReauthException $e) {
            $this->markFailed($distribution, $e->getMessage());

            return;
        } catch (PublishException $e) {
            if (! $e->retryable) {
                $this->markFailed($distribution, $e->getMessage());

                return;
            }
            $this->retryLater($distribution, $e);

            return;
        } catch (Throwable $e) {
            $this->retryLater($distribution, $e);

            return;
        }

        if ($result) {
            $this->markPublished($distribution, $result);
        }
    }

    /** Queue gave up (max exceptions, timeouts, tries): record the last error. */
    public function failed(?Throwable $e): void
    {
        $distribution = PostDistribution::find($this->distributionId);
        if ($distribution && ! $distribution->isPublished()) {
            $this->markFailed($distribution, $e?->getMessage() ?: 'Publishing failed.');
        }
    }

    /**
     * Wait for platform processing. Returns true when the job was released
     * (caller must return null), false when the caller should check again now.
     */
    protected function pollAgain(PostDistribution $distribution, int $delaySeconds): bool
    {
        $meta = (array) $distribution->meta;
        $polls = (int) ($meta['polls'] ?? 0) + 1;

        if ($polls > $this->maxPolls) {
            throw PublishException::permanent(ucfirst($this->platform())." is still processing the video after {$this->maxPolls} checks.");
        }

        $meta['polls'] = $polls;
        $released = $this->job && ! $this->job instanceof SyncJob;
        if ($released) {
            $meta['awaiting_poll'] = true;
        }
        $distribution->forceFill(['meta' => $meta, 'status' => PostDistribution::STATUS_PROCESSING])->save();

        if ($released) {
            $this->release($delaySeconds);

            return true;
        }

        Sleep::for($delaySeconds)->seconds();

        return false;
    }

    /** Merge keys into the distribution's meta and save. */
    protected function remember(PostDistribution $distribution, array $values, ?string $status = null): void
    {
        $distribution->meta = array_merge((array) $distribution->meta, $values);
        if ($status) {
            $distribution->status = $status;
        }
        $distribution->save();
    }

    protected function forget(PostDistribution $distribution, array $keys): void
    {
        $meta = (array) $distribution->meta;
        foreach ($keys as $key) {
            unset($meta[$key]);
        }
        $distribution->meta = $meta;
        $distribution->save();
    }

    protected function title(Post $post, PostDistribution $distribution): string
    {
        return trim((string) (((array) $distribution->meta)['title'] ?? $post->title ?? 'Untitled'));
    }

    /** Caption + hashtags ("#a #b"). */
    protected function caption(Post $post, PostDistribution $distribution): string
    {
        $meta = (array) $distribution->meta;
        $caption = (string) ($meta['caption'] ?? $meta['description'] ?? $post->caption ?? '');
        $tags = ! empty($meta['hashtags']) ? (string) $meta['hashtags'] : $post->hashtagLine();

        return trim($caption.($tags !== '' ? "\n\n".$tags : ''));
    }

    /** Absolute local path of the video (public disk), downloading a remote URL if needed. */
    protected function localVideoPath(Post $post): string
    {
        $path = $post->storedVideoPath();
        if ($path) {
            return Storage::disk('public')->path($path);
        }

        try {
            return VideoResolver::localPath($post);
        } catch (Throwable $e) {
            throw PublishException::permanent($e->getMessage());
        }
    }

    /** Public HTTPS URL the platform fetches the video from. */
    protected function publicVideoUrl(Post $post): string
    {
        $url = is_string($post->video_url) && str_starts_with($post->video_url, 'https://')
            ? $post->video_url
            : VideoResolver::publicUrl($post);

        if (! $url || ! str_starts_with($url, 'https://')) {
            throw PublishException::permanent(ucfirst($this->platform()).' needs a public HTTPS video URL.');
        }

        return $url;
    }

    private function retryLater(PostDistribution $distribution, Throwable $e): void
    {
        $message = $e->getMessage();
        Log::warning("[{$this->platform()}] publish attempt failed for distribution {$distribution->id}: {$message}");

        // Run directly (no queue job): there is no retry, so fail now.
        if (! $this->job) {
            $this->markFailed($distribution, $message);

            return;
        }

        $distribution->forceFill(['error' => $message, 'error_message' => $message])->save();

        throw $e; // the queue retries with backoff, then calls failed()
    }

    protected function markFailed(PostDistribution $distribution, string $message): void
    {
        Log::error("[{$this->platform()}] publish failed for distribution {$distribution->id}: {$message}");

        $meta = (array) $distribution->meta;
        unset($meta['awaiting_poll']);

        $distribution->forceFill([
            'status' => PostDistribution::STATUS_FAILED,
            'error' => $message,
            'error_message' => $message,
            'meta' => $meta,
        ])->save();
    }

    private function markPublished(PostDistribution $distribution, PublishResult $result): void
    {
        $meta = (array) $distribution->meta;
        foreach (self::TRANSIENT_META as $key) {
            unset($meta[$key]);
        }
        $meta['url'] = $result->url;
        $meta['published_via'] = $this->platform();

        $distribution->forceFill([
            'status' => PostDistribution::STATUS_PUBLISHED,
            'external_id' => $result->externalId,
            'published_at' => now(),
            'error' => null,
            'error_message' => null,
            'meta' => $meta,
        ])->save();

        $post = $distribution->post;
        if ($post) {
            $updates = [];
            if (! $post->thumbnail && $result->thumbnail) {
                $updates['thumbnail'] = $result->thumbnail;
            }
            if (! $post->published_at) {
                $updates['published_at'] = now();
            }
            if ($updates) {
                $post->forceFill($updates)->save();
            }
        }
    }
}
