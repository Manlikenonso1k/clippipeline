<?php

namespace Tests\Feature;

use App\Filament\Pages\TiktokInsights;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanAccessPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_signed_in_user_can_access_the_panel(): void
    {
        $panel = Filament::getPanel('admin');

        foreach ([true, false] as $isAdmin) {
            $this->actingAs(User::factory()->create(['is_admin' => $isAdmin]));
            $this->assertTrue(auth()->user()->canAccessPanel($panel));
        }
    }

    public function test_guests_cannot_access_the_panel(): void
    {
        $this->assertFalse((new User)->canAccessPanel(Filament::getPanel('admin')));
        $this->get('/admin')->assertRedirect();
    }

    public function test_non_admin_reaches_the_user_facing_sections(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        foreach (['/admin', '/admin/posts', '/admin/posts/create', '/admin/insights', '/admin/connect-accounts', '/admin/profile', '/admin/notifications'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_only_page_is_forbidden_for_non_admins_and_open_to_admins(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get('/admin/tiktok-insights')->assertForbidden();
        $this->assertFalse(TiktokInsights::canAccess());
    }

    public function test_admin_still_sees_everything(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->assertTrue(TiktokInsights::canAccess());
        $this->assertNotSame(403, $this->get('/admin/tiktok-insights')->status());
        $this->get('/admin/posts')->assertOk();
    }
}
