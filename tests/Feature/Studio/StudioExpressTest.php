<?php

namespace Tests\Feature\Studio;

use App\Studio\Models\StudioJob;
use App\Studio\Models\StudioProject;
use App\Studio\Models\StudioTrend;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

class StudioExpressTest extends StudioTestCase
{
    /** "All right GTA 6 is going to make a lot of millionaires. ... get your own bag. ... download the ClipPipeline app ... very easy." */
    private function words(): array
    {
        $text = 'All right, GTA 6 is going to make a lot of millionaires. And this is how you can get in line to get your own bag. All you have to do is download the ClipPipeline mobile app and upload your video and publish. Choose your template and do some motion graphics. Very easy.';
        $words = [];
        $t = 0.4;
        foreach (explode(' ', $text) as $w) {
            $words[] = ['w' => $w, 's' => round($t, 2), 'e' => round($t + 0.3, 2)];
            $t += 0.42;
        }

        return $words;
    }

    private function runWorker(StudioJob $job, array $result, array $files = []): void
    {
        $this->asWorker();
        $this->postJson('/api/worker/claim', ['worker_id' => 'w1', 'capabilities' => [$job->type]])->assertOk();
        $this->post("/api/worker/jobs/{$job->id}/complete", ['result' => json_encode($result), 'files' => $files], ['Accept' => 'application/json'])->assertOk();
        Sanctum::actingAs($this->user);
    }

    public function test_styles_and_music_library_are_public(): void
    {
        $res = $this->getJson('/api/studio/styles')->assertOk();
        $this->assertContains('vice-night', array_column($res->json('styles'), 'slug'));
        $this->assertContains('neon-drive', array_column($res->json('music'), 'slug'));
        $this->get(parse_url($res->json('music.0.url'), PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->get('/api/studio/music/nope.mp3')->assertNotFound();
    }

    public function test_make_a_video_builds_the_edit_and_renders_automatically(): void
    {
        $p = $this->postJson('/api/studio/express', [
            'style' => 'vice-night', 'music' => 'neon-drive', 'platform' => 'tiktok', 'handle' => '@nonso',
            'topic' => ['title' => 'GTA 6 is coming', 'hook' => 'GTA 6', 'hashtags' => ['gta6', 'gaming']],
        ])->assertCreated()->assertJsonPath('recipe.status', 'waiting')->json();

        $asset = $this->post("/api/studio/projects/{$p['id']}/assets", ['role' => 'source', 'file' => UploadedFile::fake()->create('me.mp4', 300, 'video/mp4')], ['Accept' => 'application/json'])->assertCreated()->json();

        $this->runWorker(StudioJob::query()->where('type', 'probe')->firstOrFail(), ['duration' => 40, 'width' => 720, 'height' => 1280, 'fps' => 30, 'has_audio' => true, 'silence' => ['starts' => [], 'ends' => []]]);
        $this->assertSame('waiting', StudioProject::find($p['id'])->recipe['status']);

        $this->runWorker(StudioJob::query()->where('type', 'transcribe')->firstOrFail(), [], [
            'transcript' => UploadedFile::fake()->createWithContent('t.json', json_encode(['language' => 'en', 'words' => $this->words()])),
        ]);

        $project = StudioProject::find($p['id']);
        $this->assertSame('rendering', $project->recipe['status']);
        $edl = $project->edl;
        $this->assertSame('vice-night', $edl['grade']);
        $this->assertTrue($edl['sfx']);
        $this->assertSame('captions-bold', $edl['captions']['template']);
        $this->assertTrue($edl['captions']['enabled']);
        $this->assertSame([['library' => 'neon-drive', 'start' => 0, 'end' => null, 'volume' => 0.45, 'duck' => true, 'fade_in' => 0.3, 'fade_out' => 1.2]], $edl['music']);

        $templates = array_column($edl['overlays'], 'template');
        $this->assertSame('hook-headline', $templates[0]);
        $this->assertSame('GTA 6', $edl['overlays'][0]['props']['title']);
        $this->assertContains('pop-headline', $templates);
        $headlines = array_map(fn ($o) => mb_strtolower($o['props']['text']), array_filter($edl['overlays'], fn ($o) => $o['template'] === 'pop-headline'));
        $this->assertNotEmpty(array_filter($headlines, fn ($h) => str_contains($h, 'bag') || str_contains($h, 'clippipeline') || str_contains($h, 'easy')));

        $outro = end($edl['main']);
        $this->assertSame('neon-outro', $outro['template']);
        $this->assertSame('@nonso', $outro['props']['title']);
        $this->assertSame('flash', $outro['transition_in']['template']);
        $this->assertGreaterThan(2, count(array_filter($edl['main'], fn ($s) => $s['type'] === 'clip')));

        $this->assertContains('gta6', $project->recipe['hashtags']);
        $this->assertContains('fyp', $project->recipe['hashtags']);
        $this->assertStringContainsString('GTA 6 is coming', $project->recipe['caption']);

        $render = StudioJob::query()->where('type', 'render_final')->firstOrFail();
        $this->assertTrue($render->input['express']);
        $claim = null;
        $this->asWorker();
        $claim = $this->postJson('/api/worker/claim', ['worker_id' => 'w1', 'capabilities' => ['render_final']])->assertOk();
        $claim->assertJsonPath('project.edl.music.0.library', 'neon-drive');
        $this->post("/api/worker/jobs/{$render->id}/complete", ['result' => json_encode(['duration' => 30, 'width' => 1080, 'height' => 1920]), 'files' => ['video' => UploadedFile::fake()->create('v.mp4', 100, 'video/mp4')]], ['Accept' => 'application/json'])->assertOk();

        Sanctum::actingAs($this->user);
        $this->withToken('');
        $this->getJson("/api/studio/projects/{$p['id']}")->assertJsonPath('recipe.status', 'done')->assertJsonPath('recipe.render_id', $project->renders()->first()->id);
        $this->assertSame($asset['id'], $project->assets()->first()->id);
    }

    public function test_video_without_speech_still_gets_made(): void
    {
        $p = $this->postJson('/api/studio/express', ['style' => 'clean-pro', 'music' => 'none'])->assertCreated()->json();
        $this->post("/api/studio/projects/{$p['id']}/assets", ['role' => 'source', 'file' => UploadedFile::fake()->create('a.mp4', 100, 'video/mp4')], ['Accept' => 'application/json'])->assertCreated();
        $this->runWorker(StudioJob::query()->where('type', 'probe')->firstOrFail(), ['duration' => 12, 'has_audio' => false]);

        $project = StudioProject::find($p['id']);
        $this->assertSame('rendering', $project->recipe['status']);
        $this->assertFalse($project->edl['captions']['enabled']);
        $this->assertSame([], $project->edl['music']);
    }

    public function test_failed_transcription_falls_back_and_remake_changes_style(): void
    {
        $p = $this->postJson('/api/studio/express', ['style' => 'vice-night'])->assertCreated()->json();
        $this->post("/api/studio/projects/{$p['id']}/assets", ['role' => 'source', 'file' => UploadedFile::fake()->create('a.mp4', 100, 'video/mp4')], ['Accept' => 'application/json'])->assertCreated();
        $this->runWorker(StudioJob::query()->where('type', 'probe')->firstOrFail(), ['duration' => 12, 'has_audio' => true]);
        $t = StudioJob::query()->where('type', 'transcribe')->firstOrFail();
        $t->forceFill(['attempts' => 3, 'status' => 'running'])->save();
        $this->asWorker();
        $this->postJson("/api/worker/jobs/{$t->id}/fail", ['error' => 'no model', 'retryable' => true])->assertJsonPath('status', 'failed');
        Sanctum::actingAs($this->user);

        $project = StudioProject::find($p['id']);
        $this->assertSame('rendering', $project->recipe['status']);
        $this->assertSame('neon-drive', $project->edl['music'][0]['library']); // style default

        $this->postJson("/api/studio/projects/{$p['id']}/express", ['style' => 'lagos-glow'])->assertStatus(409);
        $project->forceFill(['recipe' => ['status' => 'done'] + $project->recipe])->save();
        $this->postJson("/api/studio/projects/{$p['id']}/express", ['style' => 'lagos-glow', 'music' => 'chill-tape'])->assertOk()->assertJsonPath('recipe.status', 'rendering');
        $project->refresh();
        $this->assertSame('warm', $project->edl['grade']);
        $this->assertSame('chill-tape', $project->edl['music'][0]['library']);
        $this->assertSame(2, StudioJob::query()->where('type', 'render_final')->count());
    }

    public function test_unknown_style_is_rejected(): void
    {
        $this->postJson('/api/studio/express', ['style' => 'nope'])->assertStatus(422)->assertJsonValidationErrors('style');
    }

    public function test_trends_list_and_youtube_refresh(): void
    {
        StudioTrend::create(['platform' => 'tiktok', 'region' => 'NG', 'title' => 'GTA 6 trailer reactions', 'hook' => 'GTA 6', 'hashtags' => ['gta6'], 'source' => 'curated', 'score' => 5]);
        StudioTrend::create(['platform' => 'tiktok', 'region' => 'NG', 'title' => 'Old', 'source' => 'curated', 'expires_at' => now()->subDay()]);
        $this->getJson('/api/studio/trends?platform=tiktok')->assertOk()->assertJsonCount(1, 'trends')->assertJsonPath('trends.0.hook', 'GTA 6');

        config(['clippipeline.studio.youtube_api_key' => 'k']);
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [
            ['id' => 'abc', 'snippet' => ['title' => 'Big match highlights', 'tags' => ['football', 'Super Eagles']], 'statistics' => ['viewCount' => '900000']],
        ]])]);
        $this->artisan('studio:trends-refresh --regions=NG')->assertSuccessful();
        $this->getJson('/api/studio/trends?platform=youtube&region=ng')->assertOk()
            ->assertJsonPath('trends.0.title', 'Big match highlights')->assertJsonPath('trends.0.hashtags.1', 'SuperEagles')->assertJsonPath('trends.0.source', 'youtube');

        $p = $this->postJson('/api/studio/express', ['style' => 'bold-pop', 'trend_id' => StudioTrend::first()->id])->assertCreated();
        $p->assertJsonPath('recipe.topic.hook', 'GTA 6');
    }
}
