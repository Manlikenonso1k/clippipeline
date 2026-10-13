<?php

namespace Tests\Feature;

use App\Jobs\PublishToFacebookReelsJob;
use App\Jobs\PublishToInstagramReelsJob;
use App\Jobs\PublishToTikTokJob;
use App\Jobs\PublishToYouTubeShortsJob;
use App\Models\Post;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\CrossPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class CrossPostDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithAccounts(array $providers, string $status = 'active'): User
    {
        $user = User::factory()->create();
        foreach ($providers as $provider) {
            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'platform_name' => $provider,
                'access_token' => 'token',
                'status' => $status,
            ]);
        }

        return $user;
    }

    public function test_it_dispatches_a_job_per_connected_platform(): void
    {
        Queue::fake();
        $user = $this->makeUserWithAccounts(['youtube', 'instagram', 'facebook', 'tiktok']);
        $post = Post::create(['user_id' => $user->id, 'title' => 'Clip', 'video_url' => 'https://cdn.test/v.mp4', 'source' => 'upload']);

        $result = app(CrossPostService::class)->dispatch($post, ['youtube', 'instagram', 'facebook', 'tiktok'], ['title' => 'Clip']);

        Queue::assertPushed(PublishToYouTubeShortsJob::class);
        Queue::assertPushed(PublishToInstagramReelsJob::class);
        Queue::assertPushed(PublishToFacebookReelsJob::class);
        Queue::assertPushed(PublishToTikTokJob::class);
        $this->assertCount(4, $result['dispatched']);
        $this->assertSame(4, PostDistribution::where('post_id', $post->id)->where('status', 'queued')->whereNotNull('dispatched_at')->count());
    }

    public function test_it_skips_unconnected_and_inactive_platforms(): void
    {
        Queue::fake();
        $user = $this->makeUserWithAccounts(['youtube']);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'instagram', 'platform_name' => 'instagram', 'access_token' => 't', 'status' => 'needs_reauth']);
        $post = Post::create(['user_id' => $user->id, 'title' => 'Clip', 'video_url' => 'https://cdn.test/v.mp4']);

        $result = app(CrossPostService::class)->dispatch($post, ['youtube', 'instagram', 'facebook'], []);

        Queue::assertPushed(PublishToYouTubeShortsJob::class);
        Queue::assertNotPushed(PublishToInstagramReelsJob::class);
        Queue::assertNotPushed(PublishToFacebookReelsJob::class);
        $this->assertSame(['youtube'], $result['dispatched']);
        $this->assertEqualsCanonicalizing(['instagram', 'facebook'], array_column($result['skipped'], 'provider'));
    }

    public function test_it_skips_unknown_providers(): void
    {
        Queue::fake();
        $user = $this->makeUserWithAccounts(['youtube']);
        $post = Post::create(['user_id' => $user->id, 'title' => 'Clip', 'video_url' => 'https://cdn.test/v.mp4']);

        $result = app(CrossPostService::class)->dispatch($post, ['myspace'], []);

        $this->assertEmpty($result['dispatched']);
        $this->assertSame('myspace', $result['skipped'][0]['provider']);
        $this->assertDatabaseMissing('post_distributions', ['post_id' => $post->id]);
    }

    public function test_tiktok_ingest_posts_are_not_sent_back_to_tiktok(): void
    {
        Queue::fake();
        $user = $this->makeUserWithAccounts(['youtube', 'tiktok']);
        $post = Post::create(['user_id' => $user->id, 'title' => 'Clip', 'video_url' => 'https://cdn.test/v.mp4', 'source' => 'tiktok_ingest']);

        $result = app(CrossPostService::class)->dispatchAllConnected($post);

        $this->assertSame(['youtube'], $result['dispatched']);
        Queue::assertNotPushed(PublishToTikTokJob::class);
    }

    public function test_retry_resets_a_failed_distribution_and_dispatches(): void
    {
        Queue::fake();
        $user = $this->makeUserWithAccounts(['instagram']);
        $post = Post::create(['user_id' => $user->id, 'title' => 'Clip', 'video_url' => 'https://cdn.test/v.mp4']);
        $dist = PostDistribution::create([
            'post_id' => $post->id, 'platform' => 'instagram', 'status' => 'failed',
            'error_message' => 'boom', 'attempts' => 3, 'meta' => ['ig_container_id' => 'OLD', 'title' => 'keep'],
        ]);

        $fresh = app(CrossPostService::class)->retry($dist);

        $this->assertSame('queued', $fresh->status);
        $this->assertNull($fresh->error_message);
        $this->assertSame(['title' => 'keep'], $fresh->meta);
        Queue::assertPushed(PublishToInstagramReelsJob::class, fn ($job) => $job->distributionId === $dist->id);
    }

    public function test_retry_refuses_non_failed_distributions(): void
    {
        Queue::fake();
        $user = $this->makeUserWithAccounts(['youtube']);
        $post = Post::create(['user_id' => $user->id, 'title' => 'Clip', 'video_url' => 'https://cdn.test/v.mp4']);
        $dist = PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'published', 'external_id' => 'X']);

        $this->expectException(ConflictHttpException::class);
        app(CrossPostService::class)->retry($dist);
    }
}
