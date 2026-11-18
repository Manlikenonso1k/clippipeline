<?php

namespace Tests\Feature\Studio;

use App\Models\User;
use App\Studio\Models\StudioTrend;

class StudioTrendAdminTest extends StudioTestCase
{
    public function test_only_admins_manage_trends(): void
    {
        StudioTrend::create(['platform' => 'tiktok', 'region' => 'NG', 'title' => 'GTA 6 trailer reactions', 'source' => 'curated']);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin, 'web')->get('/admin/studio-trends')->assertOk()->assertSee('GTA 6 trailer reactions');
        $this->actingAs($admin, 'web')->get('/admin/studio-trends/create')->assertOk();
        $res = $this->actingAs(User::factory()->create(), 'web')->get('/admin/studio-trends');
        $this->assertContains($res->status(), [302, 403]);
        $res->assertDontSee('GTA 6 trailer reactions');
    }
}
