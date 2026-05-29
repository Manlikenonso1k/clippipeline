<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\YouTubeService;

class PublishToYouTubeShortsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $postId;

    public function __construct(int $postId)
    {
        $this->postId = $postId;
    }

    public function handle(YouTubeService $yt)
    {
        $post = DB::table('posts')->where('id', $this->postId)->first();
        if (! $post) {
            return;
        }

        $account = DB::table('social_accounts')->where('provider', 'youtube')->first();
        if (! $account) {
            DB::table('post_distributions')->updateOrInsert(
                ['post_id' => $post->id, 'platform' => 'youtube'],
                ['status' => 'failed', 'error_message' => 'No YouTube account connected', 'updated_at' => now(), 'created_at' => now()]
            );
            return;
        }

        $filePath = storage_path('app/'.ltrim($post->download_path, '\\/'));
        if (! file_exists($filePath)) {
            DB::table('post_distributions')->updateOrInsert(
                ['post_id' => $post->id, 'platform' => 'youtube'],
                ['status' => 'failed', 'error_message' => 'Downloaded file not found', 'updated_at' => now(), 'created_at' => now()]
            );
            return;
        }

        $title = trim(($post->caption ?? '') . ' #Shorts');
        $description = $post->caption ?? '';

        $externalId = $yt->uploadShort($filePath, $title, $description, $account->refresh_token ?? null);

        if (! $externalId) {
            DB::table('post_distributions')->updateOrInsert(
                ['post_id' => $post->id, 'platform' => 'youtube'],
                ['status' => 'failed', 'error_message' => 'YouTube upload failed', 'updated_at' => now(), 'created_at' => now()]
            );
            return;
        }

        DB::table('post_distributions')->updateOrInsert(
            ['post_id' => $post->id, 'platform' => 'youtube'],
            [
                'status' => 'published',
                'external_id' => $externalId,
                'published_at' => now(),
                'meta' => json_encode(['note' => 'uploaded via YouTubeService']),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
