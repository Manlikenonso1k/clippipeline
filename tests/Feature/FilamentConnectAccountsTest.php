<?php

namespace Tests\Feature;

use App\Filament\Pages\ConnectAccounts;
use App\Models\SocialAccount;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentConnectAccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->user);
    }

    public function test_page_renders_statuses_for_each_provider(): void
    {
        SocialAccount::factory()->for($this->user)->platform('youtube')->create(['handle' => '@yt_handle', 'follower_count' => 1520]);
        SocialAccount::factory()->for($this->user)->platform('facebook')->create(['handle' => 'My Page']);
        SocialAccount::factory()->for($this->user)->platform('instagram')
            ->create(['status' => 'error', 'last_error' => 'No Instagram business account linked.']);
        SocialAccount::factory()->platform('tiktok')->create(['handle' => '@someone_else']);

        $this->get(ConnectAccounts::getUrl())
            ->assertOk()
            ->assertSeeText(['YouTube', 'Facebook & Instagram', 'TikTok'])
            ->assertSeeText('@yt_handle')
            ->assertSeeText('1,520')
            ->assertSeeText('Active')
            ->assertSeeText('Error')
            ->assertSeeText('No Instagram business account linked.')
            ->assertSeeText('Not connected')
            ->assertSeeText('Reconnect')
            ->assertDontSeeText('@someone_else')
            ->assertSee(route('integrations.redirect', ['provider' => 'meta']), false)
            ->assertSee(route('integrations.redirect', ['provider' => 'tiktok']), false);
    }

    public function test_needs_reauth_status_is_shown(): void
    {
        SocialAccount::factory()->for($this->user)->platform('tiktok')->needsReauth('Refresh token is invalid')->create();

        $this->get(ConnectAccounts::getUrl())
            ->assertOk()
            ->assertSeeText('Needs reauth')
            ->assertSeeText('Refresh token is invalid');
    }

    public function test_disconnect_meta_removes_both_rows_like_the_api(): void
    {
        $fb = SocialAccount::factory()->for($this->user)->platform('facebook')->create();
        $ig = SocialAccount::factory()->for($this->user)->platform('instagram')->create();
        $yt = SocialAccount::factory()->for($this->user)->platform('youtube')->create();
        $other = SocialAccount::factory()->platform('instagram')->create();

        Livewire::test(ConnectAccounts::class)
            ->callAction('disconnect', arguments: ['account' => $other->id, 'label' => 'Instagram'])
            ->callAction('disconnect', arguments: ['account' => $ig->id, 'label' => 'Instagram']);

        $this->assertDatabaseMissing('social_accounts', ['id' => $fb->id]);
        $this->assertDatabaseMissing('social_accounts', ['id' => $ig->id]);
        $this->assertDatabaseHas('social_accounts', ['id' => $yt->id]);
        $this->assertDatabaseHas('social_accounts', ['id' => $other->id]);
    }

    public function test_rationale_youtube_tos_and_revoke_explanation_are_shown(): void
    {
        $this->get(ConnectAccounts::getUrl())
            ->assertOk()
            ->assertSeeText(ConnectAccounts::RATIONALE['youtube'])
            ->assertSeeText(ConnectAccounts::RATIONALE['meta'])
            ->assertSeeText(ConnectAccounts::RATIONALE['tiktok'])
            ->assertSeeText('YouTube Terms of Service')
            ->assertSee('https://www.youtube.com/t/terms', false)
            ->assertSee(url('/privacy'), false)
            ->assertSee(url('/terms'), false);

        $this->assertStringContainsString('revokes', ConnectAccounts::DISCONNECT_EXPLANATION);

        $yt = SocialAccount::factory()->for($this->user)->platform('youtube')->create();
        Livewire::test(ConnectAccounts::class)
            ->assertActionExists(
                TestAction::make('disconnect')->arguments(['account' => $yt->id, 'label' => 'YouTube']),
                fn (Action $action): bool => $action->getModalDescription() === ConnectAccounts::DISCONNECT_EXPLANATION,
            );
    }

    public function test_error_account_shows_its_error_text(): void
    {
        SocialAccount::factory()->for($this->user)->platform('youtube')
            ->create(['status' => 'error', 'last_error' => 'quotaExceeded on channel']);

        $this->get(ConnectAccounts::getUrl())
            ->assertOk()
            ->assertSeeText('This account has an error')
            ->assertSeeText('quotaExceeded on channel');
    }
}
