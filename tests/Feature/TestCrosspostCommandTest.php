<?php

namespace Tests\Feature;

use App\Models\PostDistribution;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connect\TokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class TestCrosspostCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_through_the_upload_flow_and_runs_jobs_inline(): void
    {
        config([
            'app.url' => 'https://clip.test',
            'services.meta.graph_version' => 'v26.0',
            'services.youtube.privacy_status' => 'public', // the command must override this
        ]);
        Storage::fake('public');
        Queue::fake();
        Sleep::fake();
        $this->mock(TokenManager::class, fn ($m) => $m->shouldReceive('freshAccessToken')->andReturn('TOKEN'));

        Http::fake([
            'clip.test/*' => Http::response('', 200),
            'www.googleapis.com/upload/youtube/*' => Http::response('', 200, ['Location' => 'https://upload.youtube.test/session/1']),
            'upload.youtube.test/*' => Http::response(['id' => 'VID123', 'status' => ['privacyStatus' => 'private']], 201),
            'graph.facebook.com/v26.0/17841400000/media*' => Http::response(['error' => ['message' => 'Media upload has failed with error code 2207026']], 400),
        ]);

        $user = User::factory()->create(['email' => 'qa@example.com']);
        foreach (['youtube' => null, 'instagram' => '17841400000'] as $provider => $externalId) {
            SocialAccount::create(['user_id' => $user->id, 'provider' => $provider, 'platform_name' => $provider,
                'access_token' => 't', 'status' => 'active', 'external_id' => $externalId]);
        }
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'tiktok', 'platform_name' => 'tiktok',
            'access_token' => 't', 'status' => 'needs_reauth']);

        $video = sys_get_temp_dir().DIRECTORY_SEPARATOR.'crosspost-'.uniqid().'.mp4';
        file_put_contents($video, str_repeat('x', 2048));

        try {
            $this->artisan('app:test-crosspost', ['user_email' => 'qa@example.com', 'video_path' => $video])
                ->expectsOutputToContain('→ youtube')
                ->expectsOutputToContain('external_id: VID123')
                ->expectsOutputToContain('url:         https://www.youtube.com/shorts/VID123')
                ->expectsOutputToContain('→ instagram')
                ->expectsOutputToContain('2207026')
                ->assertFailed();
        } finally {
            @unlink($video);
        }

        Queue::assertNothingPushed();
        $this->assertSame(['instagram', 'youtube'], PostDistribution::orderBy('platform')->pluck('platform')->all());
        $yt = PostDistribution::where('platform', 'youtube')->first();
        $this->assertSame('published', $yt->status);
        $this->assertSame('private', $yt->privacy);
        $this->assertNull($yt->post->scheduled_at);
        $this->assertSame('upload', $yt->post->source);
        $this->assertSame('failed', PostDistribution::where('platform', 'instagram')->value('status'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'uploadType=resumable') && $r['status']['privacyStatus'] === 'private');
    }
}
