<?php

namespace Tests\Feature;

use App\Filament\Pages\Insights;
use App\Filament\Widgets\InsightsTrendChart;
use App\Filament\Widgets\PlatformBreakdown;
use App\Filament\Widgets\TopPostsTable;
use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Analytics\ExportService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Insights page (PARITY I1/I2): same filters and numbers as /api/analytics/*. */
class FilamentInsightsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->user = User::factory()->create(['is_admin' => true]);
        $this->seedUser($this->user, 'Mine');
        $this->seedUser(User::factory()->create(), 'Theirs');

        $this->actingAs($this->user);
    }

    private function seedUser(User $user, string $title): void
    {
        SocialAccount::factory()->for($user)->platform('youtube')->create();
        $post = Post::create(['user_id' => $user->id, 'title' => $title, 'video_url' => 'https://cdn.test/a.mp4']);

        foreach (['youtube' => 700, 'tiktok' => 300] as $platform => $views) {
            $d = PostDistribution::create([
                'post_id' => $post->id, 'platform' => $platform, 'status' => 'published',
                'external_id' => $platform.$post->id, 'published_at' => '2026-10-10 10:00:00',
            ]);
            PostAnalytic::factory()->forDistribution($d, '2026-10-11')->create(['views' => (int) ($views / 2), 'likes' => 5, 'comments' => 1, 'shares' => 0, 'saves' => 0, 'watch_time_seconds' => 0]);
            PostAnalytic::factory()->forDistribution($d, '2026-10-13')->create(['views' => $views, 'likes' => 10, 'comments' => 2, 'shares' => 1, 'saves' => 0, 'watch_time_seconds' => 60]);
        }
    }

    public static function filters(): array
    {
        return [['7d', 'all'], ['30d', 'tiktok'], ['90d', 'youtube']];
    }

    public function test_page_renders_with_filters_and_last_updated(): void
    {
        $this->get(Insights::getUrl())
            ->assertOk()
            ->assertSeeText('Insights')
            ->assertSeeText('Platform')
            ->assertSeeText('Last updated')
            ->assertSeeText('Export CSV');

        Livewire::test(Insights::class)
            ->assertSet('filters.period', '7d')
            ->assertSet('filters.platform', 'all');
    }

    #[DataProvider('filters')]
    public function test_widgets_match_the_api(string $period, string $platform): void
    {
        $filters = ['period' => $period, 'platform' => $platform];

        $api = $this->actingAs($this->user, 'sanctum')->getJson("/api/analytics/timeseries?period={$period}&platform={$platform}")->assertOk()->json();
        $chart = Livewire::test(InsightsTrendChart::class, ['pageFilters' => $filters]);
        $this->assertEquals($api, $chart->instance()->timeseries());

        $api = $this->getJson("/api/analytics/platforms?period={$period}")->assertOk()->json();
        $this->assertEquals($api, Livewire::test(PlatformBreakdown::class, ['pageFilters' => $filters])->instance()->platforms());

        $api = $this->getJson("/api/analytics/top-posts?period={$period}&limit=".TopPostsTable::LIMIT)->assertOk()->json();
        $top = Livewire::test(TopPostsTable::class, ['pageFilters' => $filters]);
        $this->assertEquals($api, $top->instance()->topPosts());
        $top->assertSeeText('Mine')->assertDontSeeText('Theirs');
    }

    public function test_csv_export_matches_the_export_service(): void
    {
        $expected = app(ExportService::class)->csv($this->user, '30d');
        $this->assertStringContainsString('Mine', $expected);
        $this->assertStringNotContainsString('Theirs', $expected);

        Livewire::test(Insights::class)
            ->set('filters.period', '30d')
            ->callAction('export')
            ->assertFileDownloaded(ExportService::filename('30d'), $expected);
    }

    public function test_csv_export_matches_the_api_when_available(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->get('/api/analytics/export?period=7d');
        if ($response->status() === 404) {
            $this->markTestSkipped('GET /api/analytics/export not registered yet (BACKEND).');
        }

        Livewire::test(Insights::class)
            ->callAction('export')
            ->assertFileDownloaded(ExportService::filename('7d'), (string) $response->assertOk()->getContent());
    }
}
