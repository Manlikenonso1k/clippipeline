<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AnalyticsOverview;
use App\Filament\Widgets\PlatformBreakdown;
use App\Filament\Widgets\SyncStatus;
use App\Filament\Widgets\TopPostsTable;
use App\Filament\Widgets\ViewsTrendChart;
use App\Models\AccountFollowerSnapshot;
use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Account\MeService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The dashboard widgets must show exactly what /api/analytics/* returns (both use AnalyticsService). */
class FilamentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->user = User::factory()->create(['is_admin' => true]);
        $this->seedAnalytics($this->user);
        $this->seedAnalytics(User::factory()->create()); // another user's numbers must not leak

        $this->actingAs($this->user);
        Sanctum::actingAs($this->user);
    }

    private function seedAnalytics(User $user): void
    {
        $yt = SocialAccount::factory()->for($user)->platform('youtube')->create();
        SocialAccount::factory()->for($user)->platform('tiktok')->needsReauth()->create();
        AccountFollowerSnapshot::factory()->create(['social_account_id' => $yt->id, 'followers' => 1000, 'captured_on' => '2026-10-01']);
        AccountFollowerSnapshot::factory()->create(['social_account_id' => $yt->id, 'followers' => 1150, 'captured_on' => '2026-10-12']);

        $a = Post::create(['user_id' => $user->id, 'title' => 'Alpha', 'video_url' => 'https://cdn.test/a.mp4']);
        $b = Post::create(['user_id' => $user->id, 'title' => 'Beta', 'video_url' => 'https://cdn.test/b.mp4']);

        $dA = $this->dist($a, 'youtube', '2026-09-01 10:00:00');
        $dB = $this->dist($b, 'tiktok', '2026-10-10 10:00:00');
        $dB2 = $this->dist($b, 'instagram', '2026-10-10 10:00:00');

        $this->snap($dA, '2026-08-01', ['views' => 50]);
        $this->snap($dA, '2026-09-29', ['views' => 100, 'likes' => 10, 'comments' => 5, 'watch_time_seconds' => 100]);
        $this->snap($dA, '2026-10-06', ['views' => 1100, 'likes' => 50, 'comments' => 17, 'watch_time_seconds' => 3700]);
        $this->snap($dA, '2026-10-13', ['views' => 2300, 'likes' => 130, 'comments' => 29, 'shares' => 5, 'saves' => 3, 'watch_time_seconds' => 9100]);
        $this->snap($dB, '2026-10-11', ['views' => 400, 'likes' => 40, 'shares' => 4]);
        $this->snap($dB, '2026-10-13', ['views' => 900, 'likes' => 70, 'shares' => 9]);
        $this->snap($dB2, '2026-10-12', ['views' => 300, 'saves' => 7]);
    }

    private function dist(Post $post, string $platform, string $publishedAt): PostDistribution
    {
        return PostDistribution::create([
            'post_id' => $post->id, 'platform' => $platform, 'status' => 'published',
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

    public static function periods(): array
    {
        return [['7d'], ['30d'], ['90d']];
    }

    #[DataProvider('periods')]
    public function test_overview_widget_matches_api(string $period): void
    {
        $api = $this->getJson("/api/analytics/overview?period={$period}")->assertOk()->json();
        $this->assertGreaterThan(0, $api['totals']['views']);

        $widget = Livewire::test(AnalyticsOverview::class, ['pageFilters' => ['period' => $period]]);

        $this->assertEquals($api, $widget->instance()->overview());
        $widget->assertSeeText(number_format($api['totals']['views']))
            ->assertSeeText(number_format($api['totals']['likes']))
            ->assertSeeText(number_format($api['followers']['total']));

        if ($api['change']['views'] !== null) {
            $widget->assertSeeText(number_format($api['change']['views'], 1).'%');
        }
    }

    #[DataProvider('periods')]
    public function test_platform_widget_matches_api(string $period): void
    {
        $api = $this->getJson("/api/analytics/platforms?period={$period}")->assertOk()->json();

        $widget = Livewire::test(PlatformBreakdown::class, ['pageFilters' => ['period' => $period]]);

        $this->assertEquals($api, $widget->instance()->platforms());
        foreach ($api['platforms'] as $row) {
            $widget->assertSeeText(ucfirst($row['platform']));
        }
        $widget->assertSeeText(number_format($api['platforms'][3]['totals']['views'])); // tiktok
    }

    #[DataProvider('periods')]
    public function test_timeseries_chart_matches_api_for_all_and_single_platform(string $period): void
    {
        $widget = Livewire::test(ViewsTrendChart::class, ['pageFilters' => ['period' => $period]]);

        $api = $this->getJson("/api/analytics/timeseries?period={$period}")->assertOk()->json();
        $this->assertEquals($api, $widget->instance()->timeseries());

        $widget->set('filter', 'tiktok');
        $api = $this->getJson("/api/analytics/timeseries?period={$period}&platform=tiktok")->assertOk()->json();
        $this->assertEquals($api, $widget->instance()->timeseries());
    }

    #[DataProvider('periods')]
    public function test_top_posts_widget_matches_api(string $period): void
    {
        $api = $this->getJson("/api/analytics/top-posts?period={$period}&limit=".TopPostsTable::LIMIT)->assertOk()->json();
        $this->assertNotEmpty($api);

        $widget = Livewire::test(TopPostsTable::class, ['pageFilters' => ['period' => $period]]);

        $this->assertEquals($api, $widget->instance()->topPosts());
        foreach ($api as $row) {
            $widget->assertSeeText($row['title']);
        }
    }

    public function test_user_without_data_sees_zeros_not_sample_numbers(): void
    {
        $empty = User::factory()->create(['is_admin' => true]);
        $this->actingAs($empty);
        Sanctum::actingAs($empty);

        $api = $this->getJson('/api/analytics/overview?period=30d')->json();
        $overview = Livewire::test(AnalyticsOverview::class, ['pageFilters' => ['period' => '30d']])->instance()->overview();

        $this->assertEquals($api, $overview);
        $this->assertSame(0, array_sum($overview['totals']));
        $this->assertSame([], Livewire::test(TopPostsTable::class)->instance()->topPosts());
    }

    public function test_dashboard_page_renders(): void
    {
        $this->get('/admin')->assertOk()
            ->assertSeeText('Home')
            ->assertSeeText('Privacy')
            ->assertSeeText('ClipPipeline is not affiliated with TikTok, Meta or YouTube.');
    }

    public function test_home_defaults_to_7d(): void
    {
        Livewire::test(Dashboard::class)->assertSet('filters.period', '7d');
    }

    public function test_sync_status_matches_me(): void
    {
        $me = app(MeService::class)->present($this->user);
        $api = $this->getJson('/api/me')->assertOk()->json();
        if (isset($api['sync'])) {
            $this->assertEquals($api['sync'], $me['sync']);
        }

        $widget = Livewire::test(SyncStatus::class);
        $this->assertEquals($me['sync'], $widget->instance()->sync());
        // TikTok needs reauth in the seed → "attention".
        $this->assertSame('attention', $me['sync']['status']);
        $widget->assertSeeText('Needs attention');
    }

    public function test_navigation_matches_the_app_tabs(): void
    {
        $this->get('/admin')->assertOk();

        $labels = collect(Filament::getNavigation())
            ->flatMap(fn ($group) => $group->getItems())
            ->sortBy(fn ($item) => $item->getSort())
            ->map(fn ($item) => $item->getLabel())
            ->values()
            ->all();

        $this->assertSame(['Home', 'Posts', 'Connect', 'Insights', 'Profile'], array_slice($labels, 0, 5));
    }
}
