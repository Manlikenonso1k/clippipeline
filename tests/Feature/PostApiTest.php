<?php

namespace Tests\Feature;

use App\Jobs\PublishToInstagramReelsJob;
use App\Jobs\PublishToTikTokJob;
use App\Jobs\PublishToYouTubeShortsJob;
use App\Models\Post;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $headStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://clip.test']);
        Storage::fake('public');
        Queue::fake();
        Http::fake(['clip.test/*' => fn () => Http::response('', $this->headStatus)]);

        $this->user = User::factory()->create();
        foreach (['youtube', 'instagram', 'tiktok'] as $p) {
            SocialAccount::create(['user_id' => $this->user->id, 'provider' => $p, 'platform_name' => $p, 'access_token' => 't', 'status' => 'active']);
        }
        Sanctum::actingAs($this->user);
    }

    private function video(): UploadedFile
    {
        return UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4');
    }

    public function test_store_uploads_once_and_queues_one_job_per_platform(): void
    {
        $response = $this->post('/api/posts', [
            'video' => $this->video(),
            'title' => 'My clip',
            'caption' => 'Look at this',
            'hashtags' => '#fyp, clips  #fyp',
            'platforms' => ['youtube', 'instagram'],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('title', 'My clip')
            ->assertJsonPath('source', 'upload')
            ->assertJsonPath('status', 'processing')
            ->assertJsonPath('hashtags', ['fyp', 'clips'])
            ->assertJsonPath('thumbnail', null)
            ->assertJsonPath('totals.views', 0)
            ->assertJsonCount(2, 'distributions')
            ->assertJsonPath('distributions.0.status', 'queued')
            ->assertJsonPath('distributions.0.attempts', 0)
            ->assertJsonPath('analytics', []);

        $post = Post::firstOrFail();
        $this->assertStringStartsWith('https://clip.test/storage/videos/', $post->video_url);
        Storage::disk('public')->assertExists($post->video_path);
        $this->assertSame($post->video_url, $response->json('video_url'));

        Http::assertSent(fn ($r) => $r->method() === 'HEAD' && $r->url() === $post->video_url);
        Queue::assertPushed(PublishToYouTubeShortsJob::class, 1);
        Queue::assertPushed(PublishToInstagramReelsJob::class, 1);
        Queue::assertNotPushed(PublishToTikTokJob::class);

        $this->assertSame(
            ['id', 'post_id', 'platform', 'status', 'external_id', 'url', 'error_message', 'privacy', 'attempts', 'published_at', 'updated_at'],
            array_keys($response->json('distributions.0')),
        );
    }

    public function test_store_validates_input(): void
    {
        $this->postJson('/api/posts', [])->assertStatus(422)
            ->assertJsonValidationErrors(['video', 'title', 'platforms']);

        $this->post('/api/posts', [
            'video' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            'title' => str_repeat('a', 101),
            'platforms' => ['myspace'],
            'scheduled_at' => now()->subHour()->toIso8601String(),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['video', 'title', 'platforms.0', 'scheduled_at']);

        Queue::assertNothingPushed();
    }

    public function test_store_rejects_platforms_the_user_has_not_connected(): void
    {
        // facebook not connected for this user; another user's facebook does not count.
        $other = User::factory()->create();
        SocialAccount::create(['user_id' => $other->id, 'provider' => 'facebook', 'platform_name' => 'facebook', 'access_token' => 't', 'status' => 'active']);
        SocialAccount::where('user_id', $this->user->id)->where('provider', 'tiktok')->update(['status' => 'needs_reauth']);

        $this->post('/api/posts', [
            'video' => $this->video(), 'title' => 'x', 'platforms' => ['facebook', 'tiktok'],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['platforms.0', 'platforms.1']);

        $this->assertSame(0, Post::count());
    }

    public function test_store_fails_when_the_video_url_is_not_publicly_reachable(): void
    {
        $this->headStatus = 404;

        $this->post('/api/posts', [
            'video' => $this->video(), 'title' => 'x', 'platforms' => ['youtube'],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['video']);

        $this->assertSame(0, Post::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_scheduled_post_is_not_dispatched_until_due(): void
    {
        $when = now()->addHour();

        $this->post('/api/posts', [
            'video' => $this->video(), 'title' => 'Later', 'platforms' => ['youtube', 'tiktok'],
            'scheduled_at' => $when->toIso8601String(),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status', 'scheduled');

        Queue::assertNothingPushed();

        $this->artisan('posts:dispatch-scheduled')->assertSuccessful();
        Queue::assertNothingPushed();

        $this->travelTo($when->copy()->addMinute());
        $this->artisan('posts:dispatch-scheduled')->assertSuccessful();
        Queue::assertPushed(PublishToYouTubeShortsJob::class, 1);
        Queue::assertPushed(PublishToTikTokJob::class, 1);

        // Running again does not double-dispatch.
        $this->artisan('posts:dispatch-scheduled')->assertSuccessful();
        Queue::assertPushed(PublishToYouTubeShortsJob::class, 1);
        $this->assertSame(2, PostDistribution::whereNotNull('dispatched_at')->count());
    }

    public function test_index_is_scoped_and_filters_on_derived_status(): void
    {
        $published = Post::factory()->upload()->create(['user_id' => $this->user->id]);
        PostDistribution::create(['post_id' => $published->id, 'platform' => 'youtube', 'status' => 'published', 'external_id' => 'V', 'published_at' => now()]);

        $partial = Post::factory()->upload()->create(['user_id' => $this->user->id]);
        PostDistribution::create(['post_id' => $partial->id, 'platform' => 'youtube', 'status' => 'published', 'external_id' => 'V2']);
        PostDistribution::create(['post_id' => $partial->id, 'platform' => 'instagram', 'status' => 'failed', 'error_message' => 'x']);

        $failed = Post::factory()->upload()->create(['user_id' => $this->user->id]);
        PostDistribution::create(['post_id' => $failed->id, 'platform' => 'tiktok', 'status' => 'failed']);

        $scheduled = Post::factory()->upload()->create(['user_id' => $this->user->id, 'scheduled_at' => now()->addDay()]);
        PostDistribution::create(['post_id' => $scheduled->id, 'platform' => 'tiktok', 'status' => 'queued']);

        $foreign = Post::factory()->upload()->create();

        $this->getJson('/api/posts')->assertOk()->assertJsonCount(4);
        $this->getJson('/api/posts?filter=published')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $published->id);
        $this->getJson('/api/posts?filter=failed')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $failed->id);
        $this->getJson('/api/posts?filter=scheduled')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $scheduled->id);
        $this->getJson('/api/posts?filter=processing')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/posts?filter=bogus')->assertStatus(422);

        $this->getJson('/api/posts/'.$partial->id)->assertOk()->assertJsonPath('status', 'partial');
        $this->getJson('/api/posts/'.$foreign->id)->assertNotFound()->assertJsonPath('message', 'Not found.');
    }

    public function test_retry_endpoint(): void
    {
        $post = Post::factory()->upload()->create(['user_id' => $this->user->id]);
        $failed = PostDistribution::create(['post_id' => $post->id, 'platform' => 'instagram', 'status' => 'failed', 'error_message' => 'Media upload has failed with error code 2207026']);
        $published = PostDistribution::create(['post_id' => $post->id, 'platform' => 'youtube', 'status' => 'published', 'external_id' => 'V']);

        $foreignPost = Post::factory()->upload()->create();
        $foreign = PostDistribution::create(['post_id' => $foreignPost->id, 'platform' => 'youtube', 'status' => 'failed']);

        $this->postJson("/api/distributions/{$foreign->id}/retry")->assertNotFound();
        $this->postJson("/api/distributions/{$published->id}/retry")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Only failed distributions can be retried.']);

        $this->postJson("/api/distributions/{$failed->id}/retry")
            ->assertOk()
            ->assertJsonPath('id', $failed->id)
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('error_message', null);

        Queue::assertPushed(PublishToInstagramReelsJob::class, fn ($job) => $job->distributionId === $failed->id);
        Queue::assertNotPushed(PublishToYouTubeShortsJob::class);
    }

    public function test_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/posts', ['Authorization' => ''])->assertUnauthorized();
    }
}
