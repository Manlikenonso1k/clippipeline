<?php

namespace Tests\Feature;

use App\Jobs\PublishToInstagramReelsJob;
use App\Jobs\PublishToYouTubeShortsJob;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\CrossPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CrossPostDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithAccounts(array $providers): User
    {
        $user = User::factory()->create();
        foreach ($providers as $provider) {
            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'platform_name' => $provider,
                'access_token' => 'token',
                'meta' => $provider === 'instagram' ? ['ig_user_id' => '123'] : null,
            ]);
        }

        return $user;
    }

    public function test_it_dispatches_a_job_per_connected_platform(): void
    {
        Queue::fake();
        $user = $this->makeUserWithAccounts(['youtube', 'instagram']);
        $post = Post::create(['user_id' => $user->id, 'title' => 'Clip', 'video_url' => 'https://cdn.test/v.mp4']);

        $result = app(CrossPostService::class)->dispatch($post, ['youtube', 'instagram'], ['title' => 'Clip']);

        Queue::assertPushed(PublishToYouTubeShortsJob::class);
        Queue::assertPushed(PublishToInstagramReelsJob::class);
        $this->assertEqualsCanonicalizing(['youtube', 'instagram'], $result['dispatched']);

        $this->assertDatabaseHas('post_distributions', ['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'queued']);
        $this->assertDatabaseHas('post_distributions', ['post_id' => $post->id, 'platform' => 'instagram', 'status' => 'queued']);
    }

    public function test_it_skips_unconnected_platforms(): void
    {
        Queue::fake();
        $user = $this->makeUserWithAccounts(['youtube']); // no instagram
        $post = Post::create(['user_id' => $user->id, 'title' => 'Clip', 'video_url' => 'https://cdn.test/v.mp4']);

        $result = app(CrossPostService::class)->dispatch($post, ['youtube', 'instagram'], []);

        Queue::assertPushed(PublishToYouTubeShortsJob::class);
        Queue::assertNotPushed(PublishToInstagramReelsJob::class);
        $this->assertSame(['youtube'], $result['dispatched']);
        $this->assertSame('instagram', $result['skipped'][0]['provider']);
    }

    public function test_it_skips_providers_without_a_publisher(): void
    {
        Queue::fake();
        // Facebook is registered but disabled (no publisher yet) — it must be skipped
        // with a reason and never create a queued distribution.
        $user = $this->makeUserWithAccounts(['youtube']);
        $post = Post::create(['user_id' => $user->id, 'title' => 'Clip', 'video_url' => 'https://cdn.test/v.mp4']);

        $result = app(CrossPostService::class)->dispatch($post, ['facebook'], []);

        $this->assertEmpty($result['dispatched']);
        $this->assertSame('facebook', $result['skipped'][0]['provider']);
        $this->assertNotEmpty($result['skipped'][0]['reason']);
        $this->assertDatabaseMissing('post_distributions', ['post_id' => $post->id, 'platform' => 'facebook']);
    }
}
