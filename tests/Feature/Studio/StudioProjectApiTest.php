<?php

namespace Tests\Feature\Studio;

use App\Models\User;
use App\Studio\Models\StudioJob;
use App\Studio\Models\StudioTemplate;

class StudioProjectApiTest extends StudioTestCase
{
    public function test_create_list_show_and_scope_projects(): void
    {
        $res = $this->postJson('/api/studio/projects', ['title' => 'My edit', 'aspect' => '1:1'])->assertCreated();
        $res->assertJsonPath('aspect', '1:1')->assertJsonPath('revision', 1)->assertJsonPath('edl.version', 2)->assertJsonPath('edl.aspect', '1:1');
        $id = $res->json('id');

        $this->getJson('/api/studio/projects')->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'My edit');
        $this->getJson("/api/studio/projects/{$id}")->assertOk()->assertJsonStructure(['edl', 'assets', 'active_jobs', 'style_profile']);

        $other = $this->project(User::factory()->create());
        $this->getJson("/api/studio/projects/{$other->id}")->assertNotFound();
        $this->patchJson("/api/studio/projects/{$other->id}", ['title' => 'x'])->assertNotFound();
        $this->deleteJson("/api/studio/projects/{$other->id}")->assertNotFound();
    }

    public function test_patch_edl_bumps_revision_and_stale_revision_is_409(): void
    {
        $p = $this->project();
        $edl = $p->edl;
        $edl['main'][] = ['id' => 's1', 'type' => 'template', 'template' => 'kinetic-title', 'version' => 1, 'duration' => 2.5, 'props' => ['title' => 'Hi']];

        $this->patchJson("/api/studio/projects/{$p->id}", ['edl' => $edl, 'revision' => 1])->assertOk()->assertJsonPath('revision', 2);
        $this->patchJson("/api/studio/projects/{$p->id}", ['edl' => $edl, 'revision' => 1])->assertStatus(409)->assertJsonPath('revision', 2);
        $this->patchJson("/api/studio/projects/{$p->id}", ['title' => 'Renamed'])->assertOk()->assertJsonPath('title', 'Renamed');
    }

    public function test_patch_keeps_empty_template_props_as_empty_strings(): void
    {
        // Laravel's ConvertEmptyStringsToNull turned "" into null, and the worker's template check then failed with
        // 'prop "kicker": expected string, received null'.
        $p = $this->project();
        $edl = $p->edl;
        $edl['main'][] = ['id' => 's1', 'type' => 'template', 'template' => 'kinetic-title', 'version' => 1, 'duration' => 2.5,
            'props' => ['title' => 'Owo', 'kicker' => '', 'subtitle' => '']];
        $edl['overlays'][] = ['id' => 'o1', 'template' => 'stat-counter', 'version' => 1, 'anchor' => 's1', 'offset' => 0, 'duration' => 2,
            'props' => ['value' => 10, 'prefix' => '', 'suffix' => '%', 'items' => ['a', '']]];

        $this->patchJson("/api/studio/projects/{$p->id}", ['edl' => $edl, 'revision' => 1])->assertOk();

        $saved = $p->fresh()->edl;
        $this->assertSame('', $saved['main'][0]['props']['kicker']);
        $this->assertSame('', $saved['main'][0]['props']['subtitle']);
        $this->assertSame('', $saved['overlays'][0]['props']['prefix']);
        $this->assertSame(['a', ''], $saved['overlays'][0]['props']['items']);
    }

    public function test_patch_rejects_invalid_edl_and_foreign_assets(): void
    {
        $p = $this->project();
        $bad = $p->edl;
        $bad['main'][] = ['id' => 's1', 'type' => 'clip', 'asset_id' => 1, 'in' => 5, 'out' => 2];
        $this->patchJson("/api/studio/projects/{$p->id}", ['edl' => $bad, 'revision' => 1])->assertStatus(422);

        $foreign = $this->readyAsset($this->project(User::factory()->create()));
        $edl = $p->edl;
        $edl['main'][] = ['id' => 's1', 'type' => 'clip', 'asset_id' => $foreign->id, 'in' => 0, 'out' => 2];
        $this->patchJson("/api/studio/projects/{$p->id}", ['edl' => $edl, 'revision' => 1])->assertStatus(422)->assertJsonValidationErrors('edl');
    }

    public function test_render_queues_a_job_once_per_revision_and_checks_readiness(): void
    {
        $p = $this->project();
        $this->postJson("/api/studio/projects/{$p->id}/render", ['quality' => 'preview'])->assertStatus(422);

        $a = $this->readyAsset($p, ['status' => 'processing']);
        $edl = $p->edl;
        $edl['main'][] = ['id' => 's1', 'type' => 'clip', 'asset_id' => $a->id, 'in' => 0, 'out' => 5];
        $p->forceFill(['edl' => $edl])->save();
        $this->postJson("/api/studio/projects/{$p->id}/render", ['quality' => 'preview'])->assertStatus(422);

        $a->forceFill(['status' => 'ready'])->save();
        $job = $this->postJson("/api/studio/projects/{$p->id}/render", ['quality' => 'preview'])->assertStatus(202)
            ->assertJsonPath('type', 'render_preview')->assertJsonPath('status', 'queued')->json('id');
        $this->postJson("/api/studio/projects/{$p->id}/render", ['quality' => 'preview'])->assertStatus(202)->assertJsonPath('id', $job);
        $this->getJson("/api/studio/jobs/{$job}")->assertOk()->assertJsonPath('status', 'queued');
        $this->assertSame(1, StudioJob::query()->where('type', 'render_preview')->count());
    }

    public function test_render_respects_plan_minutes(): void
    {
        config(['clippipeline.studio.plans.free.studio_render_minutes' => 1]);
        $p = $this->project();
        $a = $this->readyAsset($p, ['duration_seconds' => 600]);
        $edl = $p->edl;
        $edl['main'][] = ['id' => 's1', 'type' => 'clip', 'asset_id' => $a->id, 'in' => 0, 'out' => 120];
        $p->forceFill(['edl' => $edl])->save();

        $this->postJson("/api/studio/projects/{$p->id}/render", ['quality' => 'final'])->assertStatus(422)->assertJsonValidationErrors('quality');
    }

    public function test_apply_reference_maps_suggested_style(): void
    {
        $p = $this->project();
        $this->postJson("/api/studio/projects/{$p->id}/apply-reference")->assertStatus(422);

        $a = $this->readyAsset($p, ['duration_seconds' => 20]);
        $edl = $p->edl;
        $edl['main'][] = ['id' => 's1', 'type' => 'clip', 'asset_id' => $a->id, 'in' => 0, 'out' => 6];
        $edl['main'][] = ['id' => 's2', 'type' => 'clip', 'asset_id' => $a->id, 'in' => 8, 'out' => 14];
        $p->forceFill(['edl' => $edl, 'style_profile' => ['suggested' => ['captions_template' => 'captions-pop', 'accent' => '#f5d90a', 'punch_in' => true, 'max_segment_seconds' => 2]]])->save();

        $res = $this->postJson("/api/studio/projects/{$p->id}/apply-reference")->assertOk();
        $res->assertJsonPath('edl.captions.enabled', true)->assertJsonPath('edl.captions.template', 'captions-pop')
            ->assertJsonPath('edl.theme.accent', '#F5D90A')->assertJsonPath('edl.main.1.zoom', 1.08);
        $this->assertNotEmpty($res->json('changes'));
    }

    public function test_usage_and_templates(): void
    {
        StudioTemplate::create(['slug' => 'kinetic-title', 'version' => 1, 'name' => 'Kinetic title', 'categories' => ['Kinetic type'], 'kind' => 'segment',
            'aspects' => ['9:16', '1:1', '16:9'], 'props_schema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string', 'maxLength' => 60], 'accent' => ['type' => 'string', 'format' => 'color']]],
            'default_props' => ['title' => 'Hello', 'accent' => '#7C5CFF']]);
        StudioTemplate::create(['slug' => 'kinetic-title', 'version' => 2, 'name' => 'Kinetic title', 'categories' => ['Kinetic type'], 'kind' => 'segment',
            'aspects' => ['9:16'], 'props_schema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string', 'maxLength' => 60], 'accent' => ['type' => 'string', 'format' => 'color']]],
            'default_props' => ['title' => 'Hello', 'accent' => '#7C5CFF']]);

        $this->getJson('/api/studio/usage')->assertOk()->assertJsonPath('plan', 'free')->assertJsonPath('render_minutes_limit', 10)->assertJsonPath('ai_enabled', false);
        $this->getJson('/api/studio/templates')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('templates.0.version', 2)
            ->assertJsonPath('categories.0', ['name' => 'Kinetic type', 'count' => 1]);
        $this->getJson('/api/studio/templates?kind=overlay')->assertOk()->assertJsonCount(0, 'templates');
        $this->getJson('/api/studio/templates/kinetic-title?version=1')->assertOk()->assertJsonPath('version', 1);
        $this->postJson('/api/studio/templates/kinetic-title/customize', ['colors' => ['#ff0000'], 'text' => 'Launch day'])->assertOk()
            ->assertJsonPath('props.accent', '#FF0000')->assertJsonPath('props.title', 'Launch day')->assertJsonPath('source', 'theme');
    }

    public function test_delete_project_removes_it(): void
    {
        $p = $this->project();
        $this->deleteJson("/api/studio/projects/{$p->id}")->assertNoContent();
        $this->getJson("/api/studio/projects/{$p->id}")->assertNotFound();
    }

    public function test_studio_can_be_disabled(): void
    {
        config(['clippipeline.studio.enabled' => false]);
        $this->getJson('/api/studio/projects')->assertStatus(503);
    }
}
