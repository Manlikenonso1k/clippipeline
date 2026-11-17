<?php

namespace Tests\Feature\Studio;

use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioJob;
use App\Studio\Models\StudioTemplate;
use App\Studio\Services\JobQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class StudioWorkerApiTest extends StudioTestCase
{
    public function test_user_tokens_cannot_use_worker_api(): void
    {
        $this->postJson('/api/worker/claim', ['worker_id' => 'w1'])->assertForbidden();
    }

    public function test_claim_returns_204_when_empty_and_leases_jobs_once(): void
    {
        $p = $this->project();
        $a = $this->readyAsset($p, ['status' => 'processing']);
        app(JobQueue::class)->dispatch('probe', $this->user->id, $p->id, $a->id);

        $this->asWorker();
        $first = $this->postJson('/api/worker/claim', ['worker_id' => 'w1', 'capabilities' => ['probe']])->assertOk();
        $first->assertJsonPath('type', 'probe')->assertJsonPath('asset.id', $a->id);
        $this->assertStringContainsString('signature=', $first->json('asset.url'));
        $this->postJson('/api/worker/claim', ['worker_id' => 'w2'])->assertNoContent();

        $job = StudioJob::find($first->json('id'));
        $this->assertSame('running', $job->status);
        $this->assertSame(1, $job->attempts);
        $this->postJson("/api/worker/jobs/{$job->id}/heartbeat", ['progress' => 0.5, 'message' => 'Halfway'])->assertOk()->assertJsonPath('status', 'running');
        $this->assertSame(0.5, $job->refresh()->progress);
    }

    public function test_probe_completion_marks_ready_appends_clip_and_queues_transcribe(): void
    {
        $p = $this->project();
        $a = $this->readyAsset($p, ['status' => 'processing']);
        $job = app(JobQueue::class)->dispatch('probe', $this->user->id, $p->id, $a->id);

        $this->asWorker();
        $this->postJson('/api/worker/claim', ['worker_id' => 'w1'])->assertOk();
        $this->post("/api/worker/jobs/{$job->id}/complete", [
            'result' => json_encode(['duration' => 12.5, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'has_audio' => true, 'silence' => ['starts' => [3.0], 'ends' => [4.0], 'lufs' => -20]]),
            'files' => ['proxy' => UploadedFile::fake()->create('proxy.mp4', 10, 'video/mp4'), 'poster' => UploadedFile::fake()->image('poster.jpg')],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('status', 'done');

        $a->refresh();
        $this->assertSame('ready', $a->status);
        $this->assertSame(12.5, $a->duration_seconds);
        $this->assertNotNull($a->preview_proxy_path);
        Storage::disk('local')->assertExists($a->preview_proxy_path);
        $this->assertSame($a->id, $p->refresh()->edl['main'][0]['asset_id']);
        $this->assertSame(12.5, (float) $p->edl['main'][0]['out']);
        $this->assertDatabaseHas('studio_jobs', ['type' => 'transcribe', 'asset_id' => $a->id, 'status' => 'queued']);

        // Idempotent.
        $this->post("/api/worker/jobs/{$job->id}/complete", ['result' => '{}'], ['Accept' => 'application/json'])->assertOk();
        $this->assertCount(1, $p->refresh()->edl['main']);
    }

    public function test_new_clips_are_inserted_before_the_outro(): void
    {
        $p = $this->project();
        $edl = $p->edl;
        $edl['main'] = [
            ['id' => 'intro', 'type' => 'template', 'template' => 'kinetic-title', 'version' => 1, 'duration' => 2, 'props' => []],
            ['id' => 'outro', 'type' => 'template', 'template' => 'cta-outro', 'version' => 1, 'duration' => 3, 'props' => [], 'slot' => 'outro'],
        ];
        $p->forceFill(['edl' => $edl])->save();
        $a = $this->readyAsset($p, ['status' => 'processing']);
        $job = app(JobQueue::class)->dispatch('probe', $this->user->id, $p->id, $a->id);
        $this->asWorker();
        $this->postJson('/api/worker/claim', ['worker_id' => 'w1']);
        $this->post("/api/worker/jobs/{$job->id}/complete", ['result' => json_encode(['duration' => 5, 'has_audio' => false])], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(['intro', 'clip', 'outro'], array_map(fn ($s) => $s['type'] === 'clip' ? 'clip' : $s['id'], $p->refresh()->edl['main']));
    }

    public function test_too_long_source_is_rejected(): void
    {
        $p = $this->project();
        $a = $this->readyAsset($p, ['status' => 'processing']);
        $job = app(JobQueue::class)->dispatch('probe', $this->user->id, $p->id, $a->id);
        $this->asWorker();
        $this->postJson('/api/worker/claim', ['worker_id' => 'w1']);
        $this->post("/api/worker/jobs/{$job->id}/complete", ['result' => json_encode(['duration' => 3600, 'has_audio' => true])], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('failed', $a->refresh()->status);
        $this->assertStringContainsString('minutes', $a->error);
    }

    public function test_reference_analysis_deletes_media_and_keeps_profile(): void
    {
        $p = $this->project();
        $a = $this->readyAsset($p, ['role' => 'reference']);
        $job = app(JobQueue::class)->dispatch('analyze_reference', $this->user->id, $p->id, $a->id);
        $this->asWorker();
        $this->postJson('/api/worker/claim', ['worker_id' => 'w1']);
        $this->post("/api/worker/jobs/{$job->id}/complete", [
            'result' => json_encode(['style_profile' => ['pace' => 'fast', 'suggested' => ['captions_template' => 'captions-pop']]]),
            'files' => ['contact_sheet' => UploadedFile::fake()->image('c.jpg')],
        ], ['Accept' => 'application/json'])->assertOk();

        $a->refresh();
        $this->assertNull($a->path);
        Storage::disk('local')->assertMissing("studio/{$this->user->id}/assets/a.mp4");
        $this->assertSame('fast', $p->refresh()->style_profile['pace']);
        $this->assertSame($a->id, $p->reference_asset_id);
        Storage::disk('local')->assertExists($p->style_profile['contact_sheet_path']);
    }

    public function test_render_completion_creates_render_listed_with_download_url(): void
    {
        $p = $this->project();
        $job = app(JobQueue::class)->dispatch('render_preview', $this->user->id, $p->id, null, ['quality' => 'preview', 'revision' => 1, 'edl' => $p->edl]);
        $this->asWorker();
        $claimed = $this->postJson('/api/worker/claim', ['worker_id' => 'w1'])->assertOk();
        $claimed->assertJsonPath('project.revision', 1)->assertJsonPath('project.edl.version', 2);
        $this->post("/api/worker/jobs/{$job->id}/complete", [
            'result' => json_encode(['duration' => 8.2, 'width' => 540, 'height' => 960, 'check' => ['lufs' => -14.1, 'true_peak' => -1.3]]),
            'files' => ['video' => UploadedFile::fake()->create('out.mp4', 100, 'video/mp4'), 'poster' => UploadedFile::fake()->image('p.jpg')],
        ], ['Accept' => 'application/json'])->assertOk();

        Sanctum::actingAs($this->user);
        $this->withToken('');
        $renders = $this->getJson("/api/studio/projects/{$p->id}/renders")->assertOk()->assertJsonCount(1);
        $renders->assertJsonPath('0.quality', 'preview')->assertJsonPath('0.check.lufs', -14.1);
        $this->get($renders->json('0.download_url'))->assertOk()->assertDownload('clippipeline-'.$renders->json('0.id').'.mp4');
        $this->getJson("/api/studio/jobs/{$job->id}")->assertJsonPath('result.render_id', $renders->json('0.id'));
        $this->assertSame($renders->json('0.id'), $p->refresh()->last_render_id);
    }

    public function test_fail_retries_then_fails_and_stale_leases_requeue(): void
    {
        $p = $this->project();
        $a = $this->readyAsset($p, ['status' => 'processing']);
        $job = app(JobQueue::class)->dispatch('probe', $this->user->id, $p->id, $a->id);
        $this->asWorker();

        $this->postJson('/api/worker/claim', ['worker_id' => 'w1']);
        $this->postJson("/api/worker/jobs/{$job->id}/fail", ['error' => 'ffprobe crashed', 'retryable' => true])->assertJsonPath('status', 'queued');

        $this->postJson('/api/worker/claim', ['worker_id' => 'w1']);
        $job->refresh()->forceFill(['lease_expires_at' => now()->subMinute()])->save();
        $this->artisan('studio:requeue-stale')->assertSuccessful();
        $this->assertSame('queued', $job->refresh()->status);

        $this->postJson('/api/worker/claim', ['worker_id' => 'w1']);
        $this->postJson("/api/worker/jobs/{$job->id}/fail", ['error' => 'still broken', 'retryable' => true])->assertJsonPath('status', 'failed');
        $this->assertSame('failed', StudioAsset::find($a->id)->status);
    }

    public function test_template_preview_completion_stores_public_preview(): void
    {
        StudioTemplate::create(['slug' => 'lower-third', 'version' => 1, 'name' => 'Lower third', 'categories' => ['Shapes'], 'kind' => 'overlay',
            'aspects' => ['9:16'], 'props_schema' => ['type' => 'object', 'properties' => []], 'default_props' => []]);
        $job = app(JobQueue::class)->dispatch('render_template_preview', $this->user->id, null, null, ['slug' => 'lower-third', 'version' => 1, 'aspect' => '9:16']);
        $this->asWorker();
        $this->postJson('/api/worker/claim', ['worker_id' => 'w1']);
        $this->post("/api/worker/jobs/{$job->id}/complete", [
            'files' => ['video' => UploadedFile::fake()->create('p.mp4', 50, 'video/mp4'), 'poster' => UploadedFile::fake()->image('p.jpg')],
        ], ['Accept' => 'application/json'])->assertOk();

        $t = StudioTemplate::first();
        Storage::disk('public')->assertExists($t->preview_path);
        $this->assertArrayHasKey('9:16', $t->previews);
    }

    public function test_worker_token_command_prints_a_token(): void
    {
        $this->artisan('studio:worker-token')->assertSuccessful()->expectsOutputToContain('CLIPPIPELINE_WORKER_TOKEN');
    }
}
