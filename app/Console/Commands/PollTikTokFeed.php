<?php

namespace App\Console\Commands;

use App\Jobs\DownloadTikTokVideoJob;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PollTikTokFeed extends Command
{
    protected $signature = 'tiktok:poll-feed';
    protected $description = 'Poll TikTok feed for new videos every 10 minutes';

    public function handle()
    {
        try {
            // Get all users with TikTok connections
            $users = \App\Models\User::whereHas('socialAccounts', function ($query) {
                $query->where('provider', 'tiktok');
            })->get();

            foreach ($users as $user) {
                $this->pollUserFeed($user);
            }

            $this->info('TikTok feed poll completed successfully');
            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('TikTok poll failed: ' . $e->getMessage());
            $this->error('TikTok feed poll failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function pollUserFeed($user)
    {
        $socialAccount = $user->socialAccounts()
            ->where('provider', 'tiktok')
            ->first();

        if (!$socialAccount) {
            return;
        }

        try {
            // Fetch latest videos from TikTok using scraping proxy or API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $socialAccount->access_token,
            ])->get('https://open.tiktok.com/v1/video/list', [
                'fields' => 'id,title,desc,create_time,video.download_addr',
            ]);

            if (!$response->successful()) {
                Log::warning('TikTok API failed for user ' . $user->id . ': ' . $response->body());
                return;
            }

            $videos = $response->json('data.videos', []);

            foreach ($videos as $video) {
                $existingPost = Post::where('tiktok_id', $video['id'])
                    ->where('user_id', $user->id)
                    ->first();

                if (!$existingPost) {
                    // Create new post
                    $post = Post::create([
                        'user_id' => $user->id,
                        'tiktok_id' => $video['id'],
                        'caption' => $video['desc'] ?? '',
                        'original_url' => $video['video']['download_addr'] ?? null,
                    ]);

                    // Dispatch download job
                    DownloadTikTokVideoJob::dispatch($post);

                    Log::info('New TikTok video detected for user ' . $user->id . ': ' . $video['id']);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to poll TikTok for user ' . $user->id . ': ' . $e->getMessage());
        }
    }
}
