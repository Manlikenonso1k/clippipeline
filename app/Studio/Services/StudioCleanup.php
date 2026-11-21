<?php

namespace App\Studio\Services;

use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioProject;
use App\Studio\Models\StudioRender;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Frees disk space (config clippipeline.studio.retention). Never deletes what a user still needs:
 * the newest render of each kind per project, renders attached to a post, or media of a project in use.
 */
class StudioCleanup
{
    public const SOURCE_EXPIRED = 'Removed to save space after the project was unused for a while. Upload the video again to edit it.';

    public function __construct(private StudioFiles $files) {}

    /** @return array{count: int, bytes: int} */
    public function supersededRenders(?StudioProject $only = null, bool $dry = false): array
    {
        $hours = (int) config('clippipeline.studio.retention.superseded_render_hours', 1);
        $query = StudioRender::query()->whereNull('post_id')->where('created_at', '<', now()->subHours($hours));
        if ($only) {
            $query->where('project_id', $only->id);
        }

        // Keep the newest preview and the newest final of every project, and whatever the project points at.
        $keepQuery = StudioRender::query()->selectRaw('MAX(id) as id')->groupBy('project_id', 'quality');
        if ($only) {
            $keepQuery->where('project_id', $only->id);
        }
        $keep = $keepQuery->pluck('id')
            ->merge(StudioProject::query()->whereNotNull('last_render_id')->when($only, fn ($q) => $q->whereKey($only->id))->pluck('last_render_id'))
            ->unique()->all();

        return $this->deleteRenders($query->whereNotIn('id', $keep)->get(), $dry);
    }

    /** @return array{count: int, bytes: int} */
    public function oldPreviews(bool $dry = false): array
    {
        $days = (int) config('clippipeline.studio.retention.preview_render_days', 3);
        $renders = StudioRender::query()->where('quality', 'preview')->whereNull('post_id')
            ->where('created_at', '<', now()->subDays($days))->get();

        return $this->deleteRenders($renders, $dry);
    }

    /** Preview proxies and thumbnails of projects nobody opened recently (rebuilt on demand). @return array{count: int, bytes: int} */
    public function idleProxies(bool $dry = false): array
    {
        $assets = StudioAsset::query()->whereNotNull('preview_proxy_path')
            ->whereHas('project', fn ($q) => $this->idleSince($q, (int) config('clippipeline.studio.retention.proxy_idle_days', 7)))->get();
        $bytes = 0;
        foreach ($assets as $a) {
            $bytes += $this->size([$a->preview_proxy_path, $a->thumbs_path]);
            if (! $dry) {
                $this->files->delete([$a->preview_proxy_path, $a->thumbs_path]);
                $a->forceFill(['preview_proxy_path' => null, 'thumbs_path' => null])->save();
            }
        }

        return ['count' => $assets->count(), 'bytes' => $bytes];
    }

    /** Original uploads (video, music) of projects unused for source_idle_days. 0 disables. @return array{count: int, bytes: int} */
    public function idleSources(bool $dry = false): array
    {
        $days = (int) config('clippipeline.studio.retention.source_idle_days', 30);
        if ($days <= 0) {
            return ['count' => 0, 'bytes' => 0];
        }
        $assets = StudioAsset::query()->whereIn('role', ['source', 'music'])->whereNotNull('path')
            ->whereHas('project', fn ($q) => $this->idleSince($q, $days))->get();
        $bytes = 0;
        foreach ($assets as $a) {
            $bytes += (int) ($a->size_bytes ?: $this->size([$a->path]));
            if (! $dry) {
                $this->files->delete([$a->path, $a->preview_proxy_path, $a->thumbs_path]);
                $a->forceFill(['path' => null, 'preview_proxy_path' => null, 'thumbs_path' => null, 'status' => 'failed', 'error' => self::SOURCE_EXPIRED])->save();
            }
        }

        return ['count' => $assets->count(), 'bytes' => $bytes];
    }

    /** Run after a render finishes: drop the project's renders that the new one replaced. Never throws. */
    public function afterRender(?StudioProject $project): void
    {
        if (! $project) {
            return;
        }
        try {
            $this->supersededRenders($project);
        } catch (Throwable $e) {
            Log::warning('studio cleanup after render failed: '.$e->getMessage());
        }
    }

    /** @param  Collection<int, StudioRender>  $renders @return array{count: int, bytes: int} */
    private function deleteRenders($renders, bool $dry): array
    {
        $bytes = 0;
        foreach ($renders as $r) {
            $bytes += (int) ($r->size_bytes ?: $this->size([$r->path]));
            if (! $dry) {
                $this->files->delete([$r->path, $r->poster_path]);
                if ($r->project && $r->project->last_render_id === $r->id) {
                    $r->project->forceFill(['last_render_id' => null])->save();
                }
                $r->delete();
            }
        }

        return ['count' => $renders->count(), 'bytes' => $bytes];
    }

    private function idleSince($query, int $days)
    {
        $idle = now()->subDays($days);

        return $query->where(fn ($w) => $w->where('last_opened_at', '<', $idle)
            ->orWhere(fn ($n) => $n->whereNull('last_opened_at')->where('updated_at', '<', $idle)));
    }

    /** @param  array<int, ?string>  $paths */
    private function size(array $paths): int
    {
        $total = 0;
        foreach (array_filter($paths) as $p) {
            try {
                $total += $this->files->disk()->exists($p) ? (int) $this->files->disk()->size($p) : 0;
            } catch (Throwable) {
            }
        }

        return $total;
    }
}
