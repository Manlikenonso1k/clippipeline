<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostDistribution;
use App\Models\User;
use App\Services\Account\MeService;
use App\Services\Publishing\PublishRules;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Upload-once flow (docs/CONTRACT.md §4): store the file on the public disk,
 * prove its HTTPS URL is publicly reachable, then create one queued
 * distribution + one job per platform. No platform uploads run here.
 */
class PostUploadService
{
    public function __construct(private CrossPostService $crossPost, private PublishRules $rules) {}

    /**
     * USER_SIM BUG-4: the plan's monthly clip allowance (GET /me plan.clips_limit) is enforced
     * for the API and Filament create alike. 422 on `video`.
     *
     * @throws ValidationException
     */
    public function assertWithinPlan(User $user): void
    {
        $plan = app(MeService::class)->plan($user);
        $limit = $plan['clips_limit'];

        if ($limit !== null && $plan['clips_used'] >= $limit) {
            throw ValidationException::withMessages([
                'video' => sprintf(
                    "You've used all %d clips in your %s plan this month. Upgrade your plan or post again from %s.",
                    $limit,
                    ucfirst($plan['name']),
                    now()->addMonthNoOverflow()->startOfMonth()->toFormattedDateString(),
                ),
            ]);
        }
    }

    /**
     * @param  array<int, string>  $platforms
     * @param  array<int, string>  $hashtags  without '#'
     * @param  array<string, mixed>  $platformOptions  platform_options input (CONTRACT §12)
     * @param  ?float  $clientDurationSeconds  client-measured duration; the MP4 header wins when readable
     *
     * @throws ValidationException per-platform option/duration/cap errors (P-02/P-03/T-09)
     */
    public function create(
        User $user,
        UploadedFile $video,
        string $title,
        ?string $caption,
        array $hashtags,
        array $platforms,
        ?CarbonInterface $scheduledAt,
        array $platformOptions = [],
        ?float $clientDurationSeconds = null,
    ): Post {
        $this->assertWithinPlan($user);

        $checked = $this->rules->validate(
            $user,
            $video,
            $platforms,
            $platformOptions,
            $clientDurationSeconds,
            $caption,
            implode(' ', array_map(fn ($t) => '#'.$t, $hashtags)),
        );

        $extension = strtolower($video->getClientOriginalExtension() ?: $video->guessExtension() ?: 'mp4');
        $path = $video->storeAs('videos/'.now()->format('Y/m'), Str::uuid().'.'.$extension, 'public');
        if (! $path) {
            throw ValidationException::withMessages(['video' => 'The video could not be stored.']);
        }

        $url = $this->publicUrl($path);

        try {
            $this->assertReachable($url);
        } catch (ValidationException $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return $this->persist($user, $title, $caption, $hashtags, $url, $path, $scheduledAt, $platforms, $checked);
    }

    /**
     * Studio "Send to cross-post" (CONTRACT §17): same rules as create(), but the video is a
     * file already on disk (a Studio render). It is copied to the public disk because the
     * publishing pipeline needs a public HTTPS URL.
     *
     * @param  array<int, string>  $platforms
     * @param  array<int, string>  $hashtags
     * @param  array<string, mixed>  $platformOptions
     *
     * @throws ValidationException
     */
    public function createFromStoredFile(
        User $user,
        string $absolutePath,
        string $title,
        ?string $caption,
        array $hashtags,
        array $platforms,
        ?CarbonInterface $scheduledAt,
        array $platformOptions = [],
    ): Post {
        $this->assertWithinPlan($user);

        if (! is_file($absolutePath)) {
            throw ValidationException::withMessages(['video' => 'The rendered video is no longer available. Render it again.']);
        }

        $video = new UploadedFile($absolutePath, basename($absolutePath), 'video/mp4', null, true);
        $checked = $this->rules->validate(
            $user,
            $video,
            $platforms,
            $platformOptions,
            null,
            $caption,
            implode(' ', array_map(fn ($t) => '#'.$t, $hashtags)),
        );

        $path = 'videos/'.now()->format('Y/m').'/'.Str::uuid().'.mp4';
        $stream = fopen($absolutePath, 'rb');
        $stored = Storage::disk('public')->writeStream($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        if (! $stored) {
            throw ValidationException::withMessages(['video' => 'The video could not be stored.']);
        }

        $url = $this->publicUrl($path);

        try {
            $this->assertReachable($url);
        } catch (ValidationException $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return $this->persist($user, $title, $caption, $hashtags, $url, $path, $scheduledAt, $platforms, $checked);
    }

    /**
     * One post + one queued distribution per platform; queue the jobs now unless scheduled.
     *
     * @param  array{options: array<string, array<string, mixed>>, duration: ?float}  $checked
     */
    private function persist(User $user, string $title, ?string $caption, array $hashtags, string $url, string $path, ?CarbonInterface $scheduledAt, array $platforms, array $checked): Post
    {
        $post = DB::transaction(function () use ($user, $title, $caption, $hashtags, $url, $path, $scheduledAt, $platforms, $checked) {
            $post = Post::create([
                'user_id' => $user->id,
                'title' => $title,
                'caption' => $caption,
                'hashtags' => array_values($hashtags),
                'video_url' => $url,
                'video_path' => $path,
                'source' => Post::SOURCE_UPLOAD,
                'scheduled_at' => $scheduledAt,
                'video_duration_seconds' => $checked['duration'],
            ]);

            foreach (array_values(array_unique($platforms)) as $platform) {
                PostDistribution::create([
                    'post_id' => $post->id,
                    'platform' => $platform,
                    'status' => PostDistribution::STATUS_QUEUED,
                    'attempts' => 0,
                    'options' => $checked['options'][$platform] ?? null,
                ]);
            }

            return $post;
        });

        if (! $scheduledAt) {
            foreach ($post->distributions()->get() as $distribution) {
                $this->crossPost->queue($distribution);
            }
        }

        return $post;
    }

    /**
     * Accept hashtags[] or one comma/space separated string; store without '#'.
     *
     * @return array<int, string>
     */
    public static function normalizeHashtags(mixed $value): array
    {
        $tags = [];
        foreach ((array) $value as $item) {
            foreach (preg_split('/[\s,]+/', (string) $item) ?: [] as $part) {
                $tag = ltrim(trim($part), '#');
                if ($tag !== '' && ! in_array($tag, $tags, true)) {
                    $tags[] = $tag;
                }
            }
        }

        return $tags;
    }

    /** Absolute HTTPS URL for a public-disk path. */
    public function publicUrl(string $path): string
    {
        $url = Storage::disk('public')->url($path);
        if (! preg_match('#^https?://#i', $url)) {
            $url = rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
        }

        return preg_replace('#^http://#i', 'https://', $url);
    }

    private function assertReachable(string $url): void
    {
        try {
            $response = Http::timeout(15)->withoutRedirecting()->head($url);
            $ok = $response->successful();
            $status = $response->status();
        } catch (Throwable $e) {
            $ok = false;
            $status = $e->getMessage();
        }

        if (! $ok) {
            throw ValidationException::withMessages([
                'video' => "The uploaded video is not publicly reachable at {$url} ({$status}). Check storage:link and APP_URL.",
            ]);
        }
    }
}
