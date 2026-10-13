<?php

namespace Tests\Feature;

use App\Exceptions\NeedsReauthException;
use App\Models\AccountFollowerSnapshot;
use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Analytics\AnalyticsSync;
use App\Services\Connect\TokenManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
        config(['services.meta.graph_version' => 'v24.0']);
        $this->user = User::factory()->create(['email' => 'qa@example.com']);

        // Stub CONNECT's seam: stored token, or NeedsReauthException when empty.
        $this->mock(TokenManager::class, fn ($mock) => $mock->shouldReceive('freshAccessToken')
            ->andReturnUsing(function (SocialAccount $account): string {
                if (empty($account->access_token)) {
                    throw new NeedsReauthException('reauth');
                }

                return $account->access_token;
            }));
    }

    private function account(string $platform, array $attrs = []): SocialAccount
    {
        return SocialAccount::forceCreate(array_merge([
            'user_id' => $this->user->id, 'provider' => $platform, 'platform_name' => $platform,
            'access_token' => 'tok-'.$platform, 'status' => 'active',
        ], $attrs));
    }

    private function dist(string $platform, string $externalId, string $publishedAt = '2026-10-01 10:00:00'): PostDistribution
    {
        $post = Post::create(['user_id' => $this->user->id, 'title' => 'Clip', 'video_url' => 'https://cdn.test/v.mp4']);

        return PostDistribution::create([
            'post_id' => $post->id, 'platform' => $platform, 'status' => 'published',
            'external_id' => $externalId, 'published_at' => $publishedAt,
        ]);
    }

    private function snapshot(PostDistribution $d): PostAnalytic
    {
        return PostAnalytic::query()->where('distribution_id', $d->id)->sole();
    }

    public function test_youtube_parsing_and_followers(): void
    {
        $this->account('youtube');
        $d = $this->dist('youtube', 'abc');

        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [
                ['id' => 'abc', 'statistics' => ['viewCount' => '500', 'likeCount' => '40', 'commentCount' => '5']],
            ]]),
            'youtubeanalytics.googleapis.com/*' => Http::response([
                'columnHeaders' => [['name' => 'video'], ['name' => 'estimatedMinutesWatched'], ['name' => 'averageViewDuration'], ['name' => 'shares']],
                'rows' => [['abc', 50, 6, 3]],
            ]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['statistics' => ['subscriberCount' => '1200']]]]),
        ]);

        app(AnalyticsSync::class)->run();

        $s = $this->snapshot($d);
        $this->assertSame([500, 40, 5, 3, 0, 3000, 6], [$s->views, $s->likes, $s->comments, $s->shares, $s->saves, $s->watch_time_seconds, $s->avg_view_duration_seconds]);
        $this->assertSame('2026-10-13', substr((string) $s->captured_on, 0, 10));
        $this->assertSame(1200, AccountFollowerSnapshot::query()->sole()->followers);
        $this->assertSame(1200, SocialAccount::query()->where('provider', 'youtube')->value('follower_count'));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'youtubeanalytics') && str_contains(urldecode($r->url()), 'filters=video==abc'));
    }

    public function test_instagram_reels_parsing_uses_current_metrics(): void
    {
        $this->account('instagram', ['external_id' => '1789']);
        $d = $this->dist('instagram', 'm1');

        Http::fake([
            'graph.facebook.com/v24.0/m1/insights*' => Http::response(['data' => [
                ['name' => 'views', 'values' => [['value' => 900]]],
                ['name' => 'likes', 'values' => [['value' => 70]]],
                ['name' => 'comments', 'values' => [['value' => 9]]],
                ['name' => 'shares', 'values' => [['value' => 4]]],
                ['name' => 'saved', 'values' => [['value' => 2]]],
                ['name' => 'ig_reels_avg_watch_time', 'values' => [['value' => 4500]]],
                ['name' => 'ig_reels_video_view_total_time', 'values' => [['value' => 4000000]]],
            ]]),
            'graph.facebook.com/v24.0/1789*' => Http::response(['followers_count' => 3400, 'id' => '1789']),
        ]);

        app(AnalyticsSync::class)->run();

        $s = $this->snapshot($d);
        $this->assertSame([900, 70, 9, 4, 2, 4000, 5], [$s->views, $s->likes, $s->comments, $s->shares, $s->saves, $s->watch_time_seconds, $s->avg_view_duration_seconds]);
        $this->assertSame(3400, AccountFollowerSnapshot::query()->sole()->followers);
        Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()), 'ig_reels_video_view_total_time') && ! str_contains($r->url(), 'plays'));
    }

    public function test_facebook_reel_parsing(): void
    {
        $this->account('facebook', ['external_id' => 'page1']);
        $d = $this->dist('facebook', 'v1');

        Http::fake([
            'graph.facebook.com/v24.0/v1/video_insights*' => Http::response(['data' => [
                ['name' => 'blue_reels_play_count', 'values' => [['value' => 300]]],
                ['name' => 'post_video_view_time', 'values' => [['value' => 120000]]],
                ['name' => 'post_video_avg_time_watched', 'values' => [['value' => 4000]]],
                ['name' => 'post_video_social_actions', 'values' => [['value' => ['SHARE' => 6, 'COMMENT' => 3]]]],
                ['name' => 'post_video_likes_by_reaction_type', 'values' => [['value' => ['REACTION_LIKE' => 20, 'REACTION_LOVE' => 5]]]],
            ]]),
            'graph.facebook.com/v24.0/v1*' => Http::response(['likes' => ['summary' => ['total_count' => 22]], 'comments' => ['summary' => ['total_count' => 3]]]),
            'graph.facebook.com/v24.0/page1*' => Http::response(['followers_count' => 800, 'fan_count' => 750]),
        ]);

        app(AnalyticsSync::class)->run();

        $s = $this->snapshot($d);
        $this->assertSame([300, 25, 3, 6, 0, 120, 4], [$s->views, $s->likes, $s->comments, $s->shares, $s->saves, $s->watch_time_seconds, $s->avg_view_duration_seconds]);
        $this->assertSame(800, AccountFollowerSnapshot::query()->sole()->followers);
    }

    public function test_tiktok_parsing(): void
    {
        $this->account('tiktok');
        $d = $this->dist('tiktok', '7300');

        Http::fake([
            'open.tiktokapis.com/v2/video/query/*' => Http::response([
                'data' => ['videos' => [['id' => '7300', 'view_count' => 1000, 'like_count' => 90, 'comment_count' => 7, 'share_count' => 11]]],
                'error' => ['code' => 'ok', 'message' => ''],
            ]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['follower_count' => 5000]], 'error' => ['code' => 'ok']]),
        ]);

        app(AnalyticsSync::class)->run();

        $s = $this->snapshot($d);
        $this->assertSame([1000, 90, 7, 11], [$s->views, $s->likes, $s->comments, $s->shares]);
        $this->assertSame(5000, AccountFollowerSnapshot::query()->sole()->followers);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'video/query') && $r['filters']['video_ids'] === ['7300']);
    }

    public function test_upsert_is_idempotent_per_day(): void
    {
        $this->account('tiktok');
        $d = $this->dist('tiktok', '7300');
        $views = 100;

        Http::fake(function (Request $r) use (&$views) {
            return str_contains($r->url(), 'video/query')
                ? Http::response(['data' => ['videos' => [['id' => '7300', 'view_count' => $views]]], 'error' => ['code' => 'ok']])
                : Http::response(['data' => ['user' => ['follower_count' => 10]], 'error' => ['code' => 'ok']]);
        });

        $sync = app(AnalyticsSync::class);
        $sync->run();
        $views = 150;
        $sync->run();

        $this->assertSame(1, PostAnalytic::query()->count());
        $this->assertSame(150, $this->snapshot($d)->views);
        $this->assertSame(1, AccountFollowerSnapshot::query()->count());

        $this->travel(1)->days();
        $sync->run();
        $this->assertSame(2, PostAnalytic::query()->where('distribution_id', $d->id)->count());
    }

    public function test_rate_limit_stops_platform_and_needs_reauth_is_skipped(): void
    {
        $this->account('instagram', ['external_id' => '1789']);
        $this->account('youtube', ['access_token' => '']); // TokenManager -> NeedsReauthException
        $this->dist('instagram', 'm1');
        $this->dist('youtube', 'abc');

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['code' => 4, 'message' => 'Application request limit reached']], 400),
            '*' => Http::response([], 500),
        ]);

        $stats = app(AnalyticsSync::class)->run();

        $this->assertSame(['instagram'], $stats['rate_limited']);
        $this->assertSame(0, PostAnalytic::query()->count());
        // One insights call, then the platform is stopped (no followers call).
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'googleapis'));
    }

    public function test_youtube_quota_exceeded_stops_youtube(): void
    {
        $this->account('youtube');
        $this->dist('youtube', 'abc');
        Http::fake(['*' => Http::response(['error' => ['code' => 403, 'errors' => [['reason' => 'quotaExceeded']]]], 403)]);

        $stats = app(AnalyticsSync::class)->run();

        $this->assertSame(['youtube'], $stats['rate_limited']);
        Http::assertSentCount(1);
    }

    public function test_recent_option_only_targets_fresh_unsynced_distributions(): void
    {
        $this->account('tiktok');
        $fresh = $this->dist('tiktok', '1', '2026-10-13 10:30:00');      // 90 min ago
        $tooNew = $this->dist('tiktok', '2', '2026-10-13 11:30:00');     // 30 min ago
        $old = $this->dist('tiktok', '3', '2026-10-12 10:00:00');
        $synced = $this->dist('tiktok', '4', '2026-10-13 10:15:00');
        PostAnalytic::factory()->forDistribution($synced)->create(['updated_at' => '2026-10-13 11:20:00']);

        Http::fake(['*' => Http::response(['data' => ['videos' => [['id' => '1', 'view_count' => 5]]], 'error' => ['code' => 'ok']])]);

        $this->artisan('analytics:sync', ['--recent' => true, '--user' => 'qa@example.com'])->assertSuccessful();

        $this->assertSame(5, $this->snapshot($fresh)->views);
        $this->assertSame(0, PostAnalytic::query()->whereIn('distribution_id', [$tooNew->id, $old->id])->count());
        Http::assertSent(fn (Request $r) => $r['filters']['video_ids'] === ['1']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'user/info'));
    }

    public function test_command_rejects_unknown_user(): void
    {
        $this->artisan('analytics:sync', ['--user' => 'nobody@example.com'])->assertFailed();
    }
}
