<?php

namespace Tests\Feature;

use App\Models\AccountFollowerSnapshot;
use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    }

    private function makePost(User $user, string $title = 'Clip'): Post
    {
        return Post::create(['user_id' => $user->id, 'title' => $title, 'video_url' => 'https://cdn.test/v.mp4', 'thumbnail' => 'https://cdn.test/t.jpg']);
    }

    private function dist(Post $post, string $platform, ?string $publishedAt = '2026-09-20 10:00:00', string $status = 'published'): PostDistribution
    {
        return PostDistribution::create([
            'post_id' => $post->id, 'platform' => $platform, 'status' => $status,
            'external_id' => 'ext'.$post->id.$platform, 'published_at' => $publishedAt,
        ]);
    }

    private function snap(PostDistribution $d, string $day, array $metrics): void
    {
        PostAnalytic::factory()->forDistribution($d, $day)->create(array_merge(
            ['views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'saves' => 0, 'watch_time_seconds' => 0],
            $metrics,
        ));
    }

    private function account(User $user, string $platform, string $status = 'active'): SocialAccount
    {
        return SocialAccount::forceCreate([
            'user_id' => $user->id, 'provider' => $platform, 'platform_name' => $platform,
            'access_token' => 'tok', 'status' => $status, 'follower_count' => 999999,
        ]);
    }

    /** Seeds the worked example: prev window +1000 views, current window +1200. */
    private function seedExample(User $user): PostDistribution
    {
        $d = $this->dist($this->makePost($user), 'youtube');
        $this->snap($d, '2026-09-29', ['views' => 100, 'likes' => 10, 'comments' => 5, 'watch_time_seconds' => 100]);
        $this->snap($d, '2026-10-06', ['views' => 1100, 'likes' => 50, 'comments' => 17, 'watch_time_seconds' => 3700]);
        $this->snap($d, '2026-10-10', ['views' => 1700, 'likes' => 90, 'comments' => 20, 'shares' => 2, 'watch_time_seconds' => 6000]);
        $this->snap($d, '2026-10-13', ['views' => 2300, 'likes' => 130, 'comments' => 29, 'shares' => 5, 'saves' => 3, 'watch_time_seconds' => 9100]);

        return $d;
    }

    public function test_overview_math_previous_window_change_and_zero_fill(): void
    {
        $user = User::factory()->create();
        $this->seedExample($user);
        // A post published inside the window with no snapshots yet.
        $this->dist($this->makePost($user, 'New'), 'tiktok', '2026-10-10 09:00:00');

        $account = $this->account($user, 'youtube');
        $this->account($user, 'tiktok', 'needs_reauth');
        AccountFollowerSnapshot::factory()->create(['social_account_id' => $account->id, 'followers' => 1000, 'captured_on' => '2026-10-06']);
        AccountFollowerSnapshot::factory()->create(['social_account_id' => $account->id, 'followers' => 1100, 'captured_on' => '2026-10-12']);

        // Another user's numbers must not leak in.
        $this->seedExample(User::factory()->create());

        Sanctum::actingAs($user);
        $res = $this->getJson('/api/analytics/overview?period=7d')->assertOk();

        $res->assertJson([
            'period' => '7d',
            'start' => '2026-10-07',
            'end' => '2026-10-13',
            'totals' => ['views' => 1200, 'likes' => 80, 'comments' => 12, 'shares' => 5, 'saves' => 3, 'watch_time_seconds' => 5400],
            'previous' => ['views' => 1000, 'likes' => 40, 'comments' => 12, 'shares' => 0, 'saves' => 0, 'watch_time_seconds' => 3600],
            'followers' => ['total' => 1100, 'change' => 100],
            'posts_published' => 1,
            'connected_accounts' => 1,
            'total_views' => 1200,
            'total_likes' => 80,
            'total_comments' => 12,
            'total_shares' => 5,
        ]);

        $change = $res->json('change');
        $this->assertSame(20.0, (float) $change['views']);
        $this->assertSame(100.0, (float) $change['likes']);
        $this->assertSame(0.0, (float) $change['comments']);
        $this->assertNull($change['shares']);
        $this->assertNull($change['saves']);
        $this->assertSame(50.0, (float) $change['watch_time_seconds']);

        $trend = $res->json('trend');
        $this->assertCount(7, $trend);
        $this->assertSame(['date' => '2026-10-07', 'views' => 0], $trend[0]);
        $this->assertSame(600, $trend[3]['views']); // 10-10
        $this->assertSame(600, $trend[6]['views']); // 10-13
    }

    public function test_empty_user_gets_zeros_not_sample_data(): void
    {
        $user = User::factory()->create();
        PostAnalytic::factory()->count(3)->create(); // someone else's / legacy rows
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/analytics/overview')->assertOk();
        $this->assertSame('7d', $res->json('period'));
        $this->assertSame(0, $res->json('total_views'));
        $this->assertSame(array_fill_keys(PostAnalytic::METRICS, 0), $res->json('totals'));
        $this->assertSame(array_fill_keys(PostAnalytic::METRICS, null), $res->json('change'));
        $this->assertSame(['total' => 0, 'change' => 0], $res->json('followers'));
        $this->assertCount(7, $res->json('trend'));

        $points = $this->getJson('/api/analytics/timeseries?period=30d')->assertOk()->json('points');
        $this->assertCount(30, $points);
        $this->assertSame(['date' => '2026-09-14'] + array_fill_keys(PostAnalytic::METRICS, 0), $points[0]);

        $this->getJson('/api/analytics/top-posts')->assertOk()->assertExactJson([]);

        $platforms = $this->getJson('/api/analytics/platforms')->assertOk()->json('platforms');
        $this->assertSame(['youtube', 'facebook', 'instagram', 'tiktok'], array_column($platforms, 'platform'));
        $this->assertFalse($platforms[0]['connected']);
        $this->assertSame(0, $platforms[0]['followers']);
    }

    public function test_validation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/analytics/overview?period=14d')->assertStatus(422)->assertJsonValidationErrors('period');
        $this->getJson('/api/analytics/timeseries?platform=myspace')->assertStatus(422)->assertJsonValidationErrors('platform');
        $this->getJson('/api/analytics/top-posts?limit=21')->assertStatus(422)->assertJsonValidationErrors('limit');
        $this->getJson('/api/analytics/top-posts?limit=0')->assertStatus(422);
        $this->getJson('/api/analytics/platforms?period=1y')->assertStatus(422);
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/analytics/overview')->assertStatus(401);
    }

    public function test_platforms_and_timeseries_filter(): void
    {
        $user = User::factory()->create();
        $this->seedExample($user);
        $this->account($user, 'youtube');
        Sanctum::actingAs($user);

        $platforms = $this->getJson('/api/analytics/platforms?period=7d')->assertOk()->json('platforms');
        $this->assertTrue($platforms[0]['connected']);
        $this->assertSame(1200, $platforms[0]['totals']['views']);
        $this->assertSame(20.0, (float) $platforms[0]['change']['views']);
        $this->assertSame(0, $platforms[3]['totals']['views']);
        $this->assertNull($platforms[3]['change']['views']);

        $yt = $this->getJson('/api/analytics/timeseries?platform=youtube')->assertOk();
        $this->assertSame('youtube', $yt->json('platform'));
        $this->assertSame(1200, array_sum(array_column($yt->json('points'), 'views')));

        $tt = $this->getJson('/api/analytics/timeseries?platform=tiktok')->json('points');
        $this->assertSame(0, array_sum(array_column($tt, 'views')));
    }

    public function test_post_analytics_and_scoping(): void
    {
        $user = User::factory()->create();
        $d = $this->seedExample($user);
        $tiktok = $this->dist($d->post, 'tiktok');
        $this->snap($tiktok, '2026-10-12', ['views' => 50, 'likes' => 5]);

        Sanctum::actingAs($user);
        $res = $this->getJson("/api/posts/{$d->post_id}/analytics")->assertOk();

        $res->assertJson([
            'post_id' => $d->post_id,
            'totals' => ['views' => 2350, 'likes' => 135, 'comments' => 29, 'shares' => 5, 'saves' => 3, 'watch_time_seconds' => 9100],
            'platforms' => [
                ['platform' => 'youtube', 'distribution_id' => $d->id, 'status' => 'published', 'captured_on' => '2026-10-13',
                    'url' => 'https://www.youtube.com/watch?v='.$d->external_id, 'metrics' => ['views' => 2300]],
                ['platform' => 'tiktok', 'distribution_id' => $tiktok->id, 'captured_on' => '2026-10-12', 'metrics' => ['views' => 50]],
            ],
        ]);
        $points = $res->json('platforms.0.points');
        $this->assertCount(30, $points);
        $this->assertSame('2026-09-14', $points[0]['date']);
        // 09-14..10-13: 09-29 is the first ever snapshot (+100 from nothing), so 2300 total.
        $this->assertSame(2300, array_sum(array_column($points, 'views')));

        $other = $this->makePost(User::factory()->create());
        $this->getJson("/api/posts/{$other->id}/analytics")->assertStatus(404)->assertJson(['message' => 'Not found.']);
    }

    public function test_top_posts_ranked_by_views_gained(): void
    {
        $user = User::factory()->create();
        $this->seedExample($user); // +1200 in window
        $small = $this->dist($this->makePost($user, 'Small'), 'instagram');
        $this->snap($small, '2026-10-12', ['views' => 300]);
        $old = $this->dist($this->makePost($user, 'Old'), 'facebook');
        $this->snap($old, '2026-09-01', ['views' => 99999]); // no gain in window

        Sanctum::actingAs($user);
        $top = $this->getJson('/api/analytics/top-posts?period=7d&limit=5')->assertOk()->json();

        $this->assertCount(2, $top);
        $this->assertSame('Clip', $top[0]['title']);
        $this->assertSame(['youtube'], $top[0]['platforms']);
        $this->assertSame(1200, $top[0]['totals']['views']);
        $this->assertSame('https://cdn.test/t.jpg', $top[0]['thumbnail']);
        $this->assertSame(300, $top[1]['totals']['views']);

        $this->assertCount(1, $this->getJson('/api/analytics/top-posts?limit=1')->json());
    }
}
