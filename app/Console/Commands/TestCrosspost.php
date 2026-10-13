<?php

namespace App\Console\Commands;

use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\CrossPostService;
use App\Services\PostUploadService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Real end-to-end post: runs a local video through the same upload flow as POST /api/posts
 * (PostUploadService: public disk, HTTPS reachability check, one distribution per platform),
 * then runs every platform job synchronously in this process (no queue) and prints each
 * distribution verbatim. YouTube is forced to `private` and TikTok to `SELF_ONLY`.
 */
class TestCrosspost extends Command
{
    protected $signature = 'app:test-crosspost {user_email} {video_path}';

    protected $description = 'Upload a video through the real flow to every connected platform (YouTube private, TikTok SELF_ONLY) and print the result.';

    public function handle(PostUploadService $uploads, CrossPostService $crossPost): int
    {
        $user = User::where('email', $this->argument('user_email'))->first();
        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $videoPath = (string) $this->argument('video_path');
        if (! is_file($videoPath)) {
            $this->error("Video file not found: {$videoPath}");

            return self::FAILURE;
        }

        $platforms = SocialAccount::query()
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', SocialAccount::STATUS_ACTIVE))
            ->pluck('provider')
            ->filter(fn ($p) => in_array($p, CrossPostService::PLATFORMS, true))
            ->unique()
            ->values()
            ->all();

        if ($platforms === []) {
            $this->warn('No active connected accounts for this user.');

            return self::SUCCESS;
        }

        // Unaudited apps: never post publicly from a smoke test.
        config([
            'services.youtube.privacy_status' => 'private',
            'services.tiktok_posting.privacy_level' => 'SELF_ONLY',
        ]);

        $file = new UploadedFile($videoPath, basename($videoPath), mime_content_type($videoPath) ?: 'video/mp4', null, true);

        try {
            // A far-future schedule stops create() from queueing jobs; we run them inline below.
            $post = $uploads->create(
                $user,
                $file,
                'ClipPipeline smoke test '.now()->format('Y-m-d H:i'),
                'Automated smoke test upload.',
                ['clippipeline'],
                $platforms,
                now()->addYears(10),
            );
        } catch (ValidationException $e) {
            $this->error('Upload rejected: '.implode(' ', $e->validator->errors()->all()));

            return self::FAILURE;
        }

        $post->forceFill(['scheduled_at' => null])->save();
        $this->info("Post #{$post->id} · {$post->video_url}");
        $this->line('Platforms: '.implode(', ', $platforms));

        $failed = 0;
        foreach ($post->distributions()->orderBy('id')->get() as $distribution) {
            $this->newLine();
            $this->line("→ {$distribution->platform} (distribution #{$distribution->id})");

            $distribution->forceFill(['dispatched_at' => now()])->save();
            $jobClass = $crossPost->jobFor((string) $distribution->platform);

            try {
                // Run without a queue job: polls sleep inline and errors fail immediately.
                (new $jobClass($distribution->id))->handle();
            } catch (Throwable $e) {
                $distribution->refresh();
                if (! $distribution->isPublished()) {
                    $distribution->forceFill([
                        'status' => PostDistribution::STATUS_FAILED,
                        'error' => $e->getMessage(),
                        'error_message' => $e->getMessage(),
                    ])->save();
                }
            }

            $distribution->refresh();
            if (! $distribution->isPublished()) {
                $failed++;
            }

            $this->line('   status:      '.$distribution->status);
            $this->line('   external_id: '.($distribution->external_id ?? 'null'));
            $this->line('   url:         '.($distribution->isPublished() ? ($distribution->externalUrl() ?? 'null') : 'null'));
            $this->line('   privacy:     '.($distribution->privacy ?? 'null'));
            $this->line('   attempts:    '.(int) $distribution->attempts);
            $this->line('   error:       '.($distribution->error_message ?? 'null'));
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
