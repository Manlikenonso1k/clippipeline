<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\TikTokScraperService;
use Illuminate\Support\Facades\DB;

class DownloadTikTokVideoJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $postId;

    public function __construct(int $postId)
    {
        $this->postId = $postId;
    }

    public function handle(TikTokScraperService $scraper)
    {
        $post = DB::table('posts')->where('id', $this->postId)->first();
        if (! $post) {
            return;
        }

        $tiktokId = $post->tiktok_id;
        $path = $scraper->downloadVideo($tiktokId);

        if (! $path) {
            DB::table('post_distributions')->insert([
                'post_id' => $post->id,
                'platform' => 'tiktok',
                'status' => 'failed',
                'error_message' => 'Failed to download video',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return;
        }

        DB::table('posts')->where('id', $post->id)->update([
            'download_path' => $path,
            'downloaded_at' => now(),
            'updated_at' => now(),
        ]);

        // Dispatch publishing jobs
        PublishToYouTubeShortsJob::dispatch($post->id);
        PublishToInstagramReelsJob::dispatch($post->id);
    }
}
