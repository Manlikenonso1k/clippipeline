<?php

namespace Tests\Feature\Studio;

use App\Studio\Models\StudioProject;
use App\Studio\Models\StudioRender;
use App\Studio\Services\StudioCleanup;
use Illuminate\Support\Facades\Storage;

class StudioCleanupTest extends StudioTestCase
{
    private function render(StudioProject $p, string $quality, int $hoursAgo, array $attrs = []): StudioRender
    {
        $path = "studio/{$p->user_id}/renders/".uniqid().'.mp4';
        Storage::disk('local')->put($path, str_repeat('x', 1000));
        $r = StudioRender::create(array_merge([
            'project_id' => $p->id, 'user_id' => $p->user_id, 'quality' => $quality, 'edl_revision' => 1,
            'path' => $path, 'size_bytes' => 1000,
        ], $attrs));
        $r->forceFill(['created_at' => now()->subHours($hoursAgo)])->save();

        return $r;
    }

    public function test_replaced_renders_are_deleted_but_newest_and_current_are_kept(): void
    {
        $p = $this->project();
        $old1 = $this->render($p, 'final', 5);
        $old2 = $this->render($p, 'final', 4);
        $newest = $this->render($p, 'final', 3);
        $preview = $this->render($p, 'preview', 3);
        $recent = $this->project();
        $justMade = $this->render($recent, 'final', 0);
        $justMade2 = $this->render($recent, 'final', 0);

        $out = app(StudioCleanup::class)->supersededRenders();

        $this->assertSame(2, $out['count']);
        $this->assertSame(2000, $out['bytes']);
        $this->assertNull(StudioRender::find($old1->id));
        $this->assertNull(StudioRender::find($old2->id));
        Storage::disk('local')->assertMissing($old1->path);
        $this->assertNotNull(StudioRender::find($newest->id));
        $this->assertNotNull(StudioRender::find($preview->id));
        // Made within the last hour: kept so the user can still download the one they just replaced.
        $this->assertNotNull(StudioRender::find($justMade->id));
        $this->assertNotNull(StudioRender::find($justMade2->id));
    }

    public function test_the_projects_current_render_is_kept_even_if_older(): void
    {
        $p = $this->project();
        $current = $this->render($p, 'final', 5);
        $this->render($p, 'final', 4);
        $p->forceFill(['last_render_id' => $current->id])->save();

        app(StudioCleanup::class)->supersededRenders();

        $this->assertNotNull(StudioRender::find($current->id));
    }

    public function test_unused_uploads_expire_and_render_explains_why(): void
    {
        $p = $this->project();
        $asset = $this->readyAsset($p, ['size_bytes' => 5000]);
        $edl = $p->edl;
        $edl['main'][] = ['id' => 's1', 'type' => 'clip', 'asset_id' => $asset->id, 'in' => 0, 'out' => 5];
        $p->forceFill(['edl' => $edl, 'last_opened_at' => now()->subDays(40)])->saveQuietly();
        StudioProject::query()->whereKey($p->id)->update(['updated_at' => now()->subDays(40)]);
        $active = $this->project();
        $activeAsset = $this->readyAsset($active, ['path' => 'studio/x/keep.mp4']);
        Storage::disk('local')->put('studio/x/keep.mp4', 'x');
        $active->forceFill(['last_opened_at' => now()])->save();

        $out = app(StudioCleanup::class)->idleSources();

        $this->assertSame(1, $out['count']);
        $asset->refresh();
        $this->assertNull($asset->path);
        $this->assertSame('failed', $asset->status);
        $this->assertNotNull($activeAsset->fresh()->path);

        $this->postJson("/api/studio/projects/{$p->id}/render", ['quality' => 'final'])
            ->assertStatus(422)->assertJsonFragment(['project' => ['"clip.mp4": '.StudioCleanup::SOURCE_EXPIRED]]);
    }

    public function test_source_expiry_can_be_disabled(): void
    {
        config(['clippipeline.studio.retention.source_idle_days' => 0]);
        $p = $this->project();
        $asset = $this->readyAsset($p);
        StudioProject::query()->whereKey($p->id)->update(['updated_at' => now()->subDays(400)]);

        $this->assertSame(0, app(StudioCleanup::class)->idleSources()['count']);
        $this->assertNotNull($asset->fresh()->path);
    }

    public function test_prune_command_dry_run_deletes_nothing(): void
    {
        $p = $this->project();
        $old = $this->render($p, 'final', 5);
        $this->render($p, 'final', 4);

        $this->artisan('studio:prune', ['--dry-run' => true])->expectsOutputToContain('[dry run] would delete 1 replaced render(s)')->assertSuccessful();
        $this->assertNotNull(StudioRender::find($old->id));

        $this->artisan('studio:prune')->expectsOutputToContain('Deleted 1 replaced render(s)')->assertSuccessful();
        $this->assertNull(StudioRender::find($old->id));
    }
}
