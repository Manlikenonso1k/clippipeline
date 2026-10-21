<?php

namespace Tests\Feature;

use App\Filament\Pages\Profile;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Account\MeService;
use App\Services\Account\PreferencesService;
use App\Services\Analytics\ExportService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Profile page (PARITY R2, R4–R10; COMPLIANCE U-01, U-03, U-07, U-08). */
class FilamentProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->user = User::factory()->create(['is_admin' => true, 'name' => 'Old Name']);
        $this->actingAs($this->user);
    }

    public function test_page_renders_plan_links_and_delete(): void
    {
        Post::create(['user_id' => $this->user->id, 'title' => 'This month', 'video_url' => 'https://cdn.test/v.mp4']);
        $plan = app(MeService::class)->present($this->user)['plan'];

        $this->get(Profile::getUrl())
            ->assertOk()
            ->assertSeeText(['Account', 'Preferences', 'Plan & usage', 'Legal & support', 'Sign out', 'Delete account'])
            ->assertSeeText(ucfirst($plan['name']))
            ->assertSeeText(number_format($plan['clips_used']).' / ')
            ->assertSee($plan['billing_url'], false)
            ->assertSee(url('/privacy'), false)
            ->assertSee(url('/terms'), false)
            ->assertSee(url('/data-deletion'), false)
            ->assertSee(url('/delete-account'), false)
            ->assertSeeText('ClipPipeline is not affiliated with TikTok, Meta or YouTube.')
            ->assertDontSeeText('SOC2');
    }

    public function test_plan_matches_the_me_api(): void
    {
        $api = $this->actingAs($this->user, 'sanctum')->getJson('/api/me')->assertOk()->json();
        $page = Livewire::test(Profile::class);
        $this->assertEquals($api['plan'], $page->instance()->me()['plan']);
        $this->assertEquals($api['preferences'], $page->instance()->me()['preferences']);
    }

    public function test_saving_updates_name_and_preferences_via_the_shared_service(): void
    {
        Livewire::test(Profile::class)
            ->assertSet('data.name', 'Old Name')
            ->set('data.name', 'New Name')
            ->set('data.notifications_enabled', false)
            ->set('data.auto_sync', false)
            ->set('data.default_platforms', ['youtube', 'tiktok'])
            ->set('data.default_hashtags', ['#fyp', 'shorts'])
            ->call('save')
            ->assertHasNoErrors();

        $user = $this->user->fresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame([
            'notifications_enabled' => false,
            'auto_sync' => false,
            'default_platforms' => ['youtube', 'tiktok'],
            'default_hashtags' => ['fyp', 'shorts'],
        ], app(PreferencesService::class)->get($user));
    }

    public function test_name_is_required(): void
    {
        Livewire::test(Profile::class)
            ->set('data.name', '')
            ->call('save')
            ->assertHasErrors(['data.name']);

        $this->assertSame('Old Name', $this->user->fresh()->name);
    }

    public function test_csv_download_matches_the_export_service(): void
    {
        Livewire::test(Profile::class)
            ->callAction(TestAction::make('exportCsv'), ['period' => '30d'])
            ->assertFileDownloaded(ExportService::filename('30d'), app(ExportService::class)->csv($this->user, '30d'));
    }

    public function test_delete_account_requires_typed_confirmation(): void
    {
        Livewire::test(Profile::class)
            ->callAction(TestAction::make('deleteAccount'), ['confirm' => 'delete'])
            ->assertHasFormErrors(['confirm']);

        $this->assertModelExists($this->user);
        $this->assertNull($this->user->fresh()->deletion_requested_at);
    }

    public function test_delete_account_removes_the_users_data(): void
    {
        SocialAccount::factory()->for($this->user)->platform('youtube')->create();
        Post::create(['user_id' => $this->user->id, 'title' => 'Bye', 'video_url' => 'https://cdn.test/v.mp4']);
        $this->user->createToken('phone');
        $other = User::factory()->create();
        $otherPost = Post::create(['user_id' => $other->id, 'title' => 'Stays', 'video_url' => 'https://cdn.test/o.mp4']);

        Livewire::test(Profile::class)
            ->callAction(TestAction::make('deleteAccount'), ['confirm' => 'DELETE'])
            ->assertHasNoFormErrors()
            ->assertRedirect(url('/delete-account?status=deleted'));

        $this->assertSame(0, SocialAccount::where('user_id', $this->user->id)->count());
        $this->assertSame(0, Post::where('user_id', $this->user->id)->count());
        $this->assertSame(0, $this->user->tokens()->count());
        $this->assertModelMissing($this->user); // queue is sync in tests: the purge ran
        $this->assertModelExists($otherPost);
        $this->assertGuest();
    }
}
