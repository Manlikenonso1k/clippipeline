<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\InstagramService;
use App\Services\YouTubeService;
use App\Support\VideoResolver;
use Illuminate\Console\Command;

/**
 * Real smoke test (item 13): synchronously posts a short test video to every
 * connected platform for a user (YouTube as PRIVATE) and prints the per-platform
 * result verbatim. Does not use the queue so you see errors immediately.
 */
class TestCrosspost extends Command
{
    protected $signature = 'app:test-crosspost {user_email} {video_path}';

    protected $description = 'Post a test video to every connected platform for a user and print the result.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('user_email'))->first();
        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $videoPath = $this->argument('video_path');
        if (! is_file($videoPath)) {
            $this->error("Video file not found: {$videoPath}");

            return self::FAILURE;
        }

        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'ClipPipeline smoke test',
            'caption' => 'Automated smoke test upload.',
            'video_url' => $videoPath,
            'download_path' => null,
        ]);

        $accounts = SocialAccount::where('user_id', $user->id)->get();
        if ($accounts->isEmpty()) {
            $this->warn('No connected accounts for this user.');

            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            $this->line("→ {$account->provider}");
            try {
                $externalId = match ($account->provider) {
                    'youtube' => app(YouTubeService::class)->uploadShort(
                        $videoPath,
                        'ClipPipeline smoke test',
                        'Automated smoke test upload.',
                        $account,
                        'private',
                    ),
                    'instagram' => app(InstagramService::class)->publishReel(
                        VideoResolver::publicUrl($post) ?? '',
                        'Automated smoke test upload.',
                        $account,
                    ),
                    default => throw new \RuntimeException('No publisher for '.$account->provider),
                };
                $this->info("   OK — external id: {$externalId}");
            } catch (\Throwable $e) {
                $this->error('   FAILED — '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
