<?php

namespace Tests\Feature\Studio;

use App\Models\SocialAccount;
use App\Studio\Models\StudioRender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class StudioCrossPostTest extends StudioTestCase
{
    public function test_cross_post_creates_a_post_from_the_render(): void
    {
        Http::fake(['clip.test/*' => Http::response('', 200), 'exp.host/*' => Http::response([])]);
        SocialAccount::create(['user_id' => $this->user->id, 'provider' => 'instagram', 'platform_name' => 'instagram', 'access_token' => 't', 'status' => 'active']);
        $p = $this->project();
        Storage::disk('local')->put('studio/r.mp4', str_repeat('x', 2048));
        $r = StudioRender::create(['project_id' => $p->id, 'user_id' => $this->user->id, 'quality' => 'final', 'edl_revision' => 1, 'path' => 'studio/r.mp4', 'duration_seconds' => 20, 'created_at' => now()]);

        $this->postJson("/api/studio/renders/{$r->id}/cross-post", ['title' => 'From Studio', 'platforms' => ['youtube']])
            ->assertStatus(422)->assertJsonValidationErrors('platforms.0');

        $res = $this->postJson("/api/studio/renders/{$r->id}/cross-post", ['title' => 'From Studio', 'platforms' => ['instagram'], 'hashtags' => 'a b'])->assertCreated();
        $res->assertJsonPath('title', 'From Studio');
        $this->assertSame($res->json('id'), $r->refresh()->post_id);
        $this->assertNotEmpty(Storage::disk('public')->allFiles('videos'));
    }
}
