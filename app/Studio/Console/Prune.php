<?php

namespace App\Studio\Console;

use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioJob;
use App\Studio\Models\StudioRender;
use App\Studio\Services\StudioFiles;
use Illuminate\Console\Command;

/** Retention (STUDIO_SPEC §3): preview renders 14 days, source proxies 30 days after last open. */
class Prune extends Command
{
    protected $signature = 'studio:prune {--dry-run}';

    protected $description = 'Delete old Studio preview renders, idle preview proxies and old finished jobs.';

    public function handle(StudioFiles $files): int
    {
        $dry = (bool) $this->option('dry-run');
        $cfg = (array) config('clippipeline.studio.retention');

        $renders = StudioRender::query()->where('quality', 'preview')->whereNull('post_id')
            ->where('created_at', '<', now()->subDays((int) ($cfg['preview_render_days'] ?? 14)))->get();
        foreach ($renders as $r) {
            if (! $dry) {
                $files->delete([$r->path, $r->poster_path]);
                $r->project?->last_render_id === $r->id && $r->project->forceFill(['last_render_id' => null])->save();
                $r->delete();
            }
        }

        $idle = now()->subDays((int) ($cfg['proxy_idle_days'] ?? 30));
        $assets = StudioAsset::query()->whereNotNull('preview_proxy_path')
            ->whereHas('project', fn ($q) => $q->where(fn ($w) => $w->where('last_opened_at', '<', $idle)->orWhere(fn ($n) => $n->whereNull('last_opened_at')->where('updated_at', '<', $idle))))
            ->get();
        foreach ($assets as $a) {
            if (! $dry) {
                $files->delete([$a->preview_proxy_path, $a->thumbs_path]);
                $a->forceFill(['preview_proxy_path' => null, 'thumbs_path' => null])->save();
            }
        }

        // Reference media is deleted after analysis; this catches any that failed half-way.
        $refs = StudioAsset::query()->where('role', 'reference')->whereNotNull('path')->where('updated_at', '<', now()->subDay())->get();
        foreach ($refs as $a) {
            if (! $dry) {
                $files->delete([$a->path, $a->preview_proxy_path]);
                $a->forceFill(['path' => null, 'preview_proxy_path' => null])->save();
            }
        }

        $jobs = StudioJob::query()->whereIn('status', [StudioJob::STATUS_DONE, StudioJob::STATUS_FAILED, StudioJob::STATUS_CANCELED])
            ->where('updated_at', '<', now()->subDays(30));
        $jobCount = $dry ? $jobs->count() : $jobs->delete();

        $this->info(sprintf('%s%d preview render(s), %d proxy set(s), %d reference file(s), %d old job(s).',
            $dry ? '[dry run] would delete ' : 'Deleted ', $renders->count(), $assets->count(), $refs->count(), $jobCount));

        return self::SUCCESS;
    }
}
