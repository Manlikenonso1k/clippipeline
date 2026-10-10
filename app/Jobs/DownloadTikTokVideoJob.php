<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\CrossPostService;
use App\Services\TikTokScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        // Fan out to every connected provider for the post owner.
        $model = Post::find($post->id);
        if ($model) {
            app(CrossPostService::class)->dispatchAllConnected($model, [
                'title' => $model->title,
                'caption' => $model->caption,
            ]);
        }
    }
}
