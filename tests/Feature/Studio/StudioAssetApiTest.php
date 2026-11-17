<?php

namespace Tests\Feature\Studio;

use App\Studio\Models\StudioJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class StudioAssetApiTest extends StudioTestCase
{
    public function test_upload_queues_probe_and_returns_signed_urls(): void
    {
        $p = $this->project();
        $res = $this->post("/api/studio/projects/{$p->id}/assets", [
            'role' => 'source', 'file' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $res->assertJsonPath('status', 'processing')->assertJsonPath('role', 'source');
        $this->assertStringContainsString('signature=', $res->json('url'));
        $this->assertDatabaseHas('studio_jobs', ['type' => 'probe', 'asset_id' => $res->json('id'), 'status' => 'queued']);

        // The signed URL serves the file; an unsigned one does not.
        $this->get($res->json('url'))->assertOk();
        $this->get(strtok($res->json('url'), '?'))->assertForbidden();
    }

    public function test_upload_rejects_wrong_type_for_role(): void
    {
        $p = $this->project();
        $this->post("/api/studio/projects/{$p->id}/assets", [
            'role' => 'music', 'file' => UploadedFile::fake()->create('clip.mp4', 10, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_platform_links_are_refused_unless_fetcher_is_on(): void
    {
        $p = $this->project();
        $this->postJson("/api/studio/projects/{$p->id}/assets", ['role' => 'reference', 'url' => 'https://www.tiktok.com/@a/video/1'])
            ->assertStatus(422)->assertJsonValidationErrors('url');
        $this->postJson("/api/studio/projects/{$p->id}/assets", ['role' => 'reference', 'url' => 'https://youtu.be/abc'])->assertStatus(422);

        config(['clippipeline.studio.link_fetcher' => 'ytdlp']);
        $this->postJson("/api/studio/projects/{$p->id}/assets", ['role' => 'reference', 'url' => 'https://youtu.be/abc'])
            ->assertCreated()->assertJsonPath('status', 'fetching');
        $job = StudioJob::query()->where('type', 'fetch_link')->first();
        $this->assertTrue($job->input['ytdlp']);
    }

    public function test_direct_links_need_a_video_content_type(): void
    {
        Http::fake([
            'cdn.example.com/ok.mp4' => Http::response('', 200, ['Content-Type' => 'video/mp4', 'Content-Length' => '1000']),
            'cdn.example.com/page' => Http::response('', 200, ['Content-Type' => 'text/html']),
        ]);
        $p = $this->project();
        $this->postJson("/api/studio/projects/{$p->id}/assets", ['role' => 'source', 'url' => 'https://cdn.example.com/page'])->assertStatus(422);
        $this->postJson("/api/studio/projects/{$p->id}/assets", ['role' => 'source', 'url' => 'http://cdn.example.com/ok.mp4'])->assertStatus(422);
        $this->postJson("/api/studio/projects/{$p->id}/assets", ['role' => 'source', 'url' => 'https://cdn.example.com/ok.mp4'])
            ->assertCreated()->assertJsonPath('origin', 'link');
    }

    public function test_transcript_and_delete(): void
    {
        $p = $this->project();
        $a = $this->readyAsset($p);
        $this->getJson("/api/studio/assets/{$a->id}/transcript")->assertNotFound()->assertJsonPath('pending', false);

        \Storage::disk('local')->put('t.json', json_encode(['language' => 'en', 'words' => [['w' => 'Hi', 's' => 0.1, 'e' => 0.4]]]));
        $a->forceFill(['transcript_path' => 't.json'])->save();
        $this->getJson("/api/studio/assets/{$a->id}/transcript")->assertOk()->assertJsonPath('words.0.w', 'Hi');

        $edl = $p->edl;
        $edl['main'][] = ['id' => 's1', 'type' => 'clip', 'asset_id' => $a->id, 'in' => 0, 'out' => 5];
        $p->forceFill(['edl' => $edl])->save();
        $this->deleteJson("/api/studio/assets/{$a->id}")->assertNoContent();
        $this->assertSame([], $p->refresh()->edl['main']);
        $this->assertSame(2, $p->revision);
    }
}
