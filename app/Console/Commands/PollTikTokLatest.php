<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\TikTokScraperService;
use App\Jobs\DownloadTikTokVideoJob;

class PollTikTokLatest extends Command
{
    protected $signature = 'tiktok:poll-latest';
    protected $description = 'Poll configured users for their latest TikTok video and enqueue processing';

    public function handle(TikTokScraperService $scraper)
    {
        $this->info('Polling users for latest TikTok videos...');

        $users = DB::table('users')->whereNotNull('settings')->get();

        foreach ($users as $user) {
            $settings = json_decode($user->settings, true) ?? [];
            if (empty($settings['tiktok_username'])) {
                continue;
            }

            $username = $settings['tiktok_username'];
            $latest = $scraper->getLatestVideoId($username);
            if (! $latest) {
                $this->line("No latest found for {$username}");
                continue;
            }

            $exists = DB::table('posts')->where('tiktok_id', $latest)->exists();
            if ($exists) {
                $this->line("Already processed {$latest} for {$username}");
                continue;
            }

            $postId = DB::table('posts')->insertGetId([
                'user_id' => $user->id,
                'tiktok_id' => $latest,
                'caption' => null,
                'original_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->line("Queued post {$latest} (id: {$postId}) for download");
            DownloadTikTokVideoJob::dispatch($postId);
        }

        $this->info('Polling complete.');
    }
}
