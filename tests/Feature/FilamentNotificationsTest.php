<?php

namespace Tests\Feature;

use App\Filament\Pages\ConnectAccounts;
use App\Filament\Pages\Notifications;
use App\Filament\Resources\Posts\PostResource;
use App\Models\User;
use App\Services\NotificationFeed;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Bell + list (PARITY X2): same NotificationFeed as GET /api/notifications. */
class FilamentNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->user);

        $feed = app(NotificationFeed::class);
        $feed->store($this->user, 'Published to YouTube', 'Your clip is live.', ['type' => 'distribution_published', 'postId' => 42]);
        $feed->store($this->user, 'TikTok needs reconnecting', 'Reconnect to keep posting.', ['type' => 'account_needs_reauth', 'provider' => 'tiktok']);
        $feed->store(User::factory()->create(), 'Someone else', 'Not yours.', ['type' => 'distribution_failed']);
    }

    public function test_list_shows_the_users_feed_with_links(): void
    {
        $page = Livewire::test(Notifications::class)
            ->assertSeeText('Published to YouTube')
            ->assertSeeText('TikTok needs reconnecting')
            ->assertDontSeeText('Someone else');

        $feed = $page->instance()->feed();
        $this->assertEquals(app(NotificationFeed::class)->list($this->user, Notifications::LIMIT), $feed);
        $this->assertSame(2, $feed['unread_count']);

        $byTitle = collect($feed['items'])->keyBy('title');
        $this->assertSame(PostResource::getUrl('view', ['record' => 42]), $page->instance()->linkFor($byTitle['Published to YouTube']));
        $this->assertSame(ConnectAccounts::getUrl(), $page->instance()->linkFor($byTitle['TikTok needs reconnecting']));
    }

    public function test_feed_matches_the_api_when_available(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/notifications?limit=30');
        $this->assertEquals($response->json(), json_decode(json_encode(Livewire::test(Notifications::class)->instance()->feed()), true));
    }

    public function test_bell_shows_unread_count_and_mark_all_read_clears_it(): void
    {
        $this->assertSame(2, Notifications::unreadCount());
        $this->get(Notifications::getUrl())->assertOk()->assertSee(Notifications::getUrl(), false);

        Livewire::test(Notifications::class)->callAction('markAllRead');

        $this->assertSame(0, Notifications::unreadCount());
        $this->assertSame(0, app(NotificationFeed::class)->list($this->user)['unread_count']);
    }
}
