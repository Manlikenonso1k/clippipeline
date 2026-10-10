<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanAccessPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_can_access_the_panel(): void
    {
        $panel = Filament::getPanel('admin');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertTrue($admin->canAccessPanel($panel));
    }

    public function test_non_admin_user_cannot_access_the_panel(): void
    {
        $panel = Filament::getPanel('admin');
        $user = User::factory()->create(['is_admin' => false]);

        $this->assertFalse($user->canAccessPanel($panel));
    }
}
