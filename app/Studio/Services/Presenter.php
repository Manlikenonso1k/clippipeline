<?php

namespace App\Studio\Services;

use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioJob;
use App\Studio\Models\StudioProject;
use App\Studio\Models\StudioRender;
use App\Studio\Models\StudioTemplate;

/** JSON shapes for CONTRACT §17. */
class Presenter
{
    public function __construct(private StudioFiles $files) {}

    public function project(StudioProject $p, bool $full = false): array
    {
        $render = $p->lastRender;
        $out = [
            'id' => $p->id,
            'title' => $p->title,
            'aspect' => $p->aspect,
            'fps' => $p->fps,
            'revision' => $p->revision,
            'duration_seconds' => Edl::duration($p->edl ?? []),
            'clip_count' => count(array_filter($p->edl['main'] ?? [], fn ($s) => ($s['type'] ?? null) === 'clip')),
            'poster_url' => $render ? $this->files->renderUrl($render, 'poster') : $this->firstPoster($p),
            'reference_asset_id' => $p->reference_asset_id,
            'has_style_profile' => $p->style_profile !== null,
            'can_undo_auto_cut' => $p->edl_previous !== null,
            'last_render' => $render ? $this->render($render) : null,
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];

        if ($full) {
            $out['edl'] = $p->edl;
            $out['style_profile'] = $p->style_profile;
            $out['assets'] = $p->assets()->orderBy('id')->get()->map(fn ($a) => $this->asset($a))->all();
            $out['active_jobs'] = $p->jobs()->whereIn('status', [StudioJob::STATUS_QUEUED, StudioJob::STATUS_RUNNING])
                ->orderBy('created_at')->get()->map(fn ($j) => $this->job($j))->all();
        }

        return $out;
    }

    public function asset(StudioAsset $a): array
    {
        return [
            'id' => $a->id,
            'project_id' => $a->project_id,
            'role' => $a->role,
            'origin' => $a->origin,
            'original_name' => $a->original_name,
            'mime' => $a->mime,
            'size_bytes' => $a->size_bytes,
            'duration_seconds' => $a->duration_seconds,
            'width' => $a->width,
            'height' => $a->height,
            'fps' => $a->fps,
            'has_audio' => $a->has_audio,
            'status' => $a->status,
            'error' => $a->error,
            'url' => $this->files->assetUrl($a, 'original'),
            'preview_url' => $this->files->assetUrl($a, 'proxy') ?? ($a->isImage() ? $this->files->assetUrl($a, 'original') : null),
            'poster_url' => $this->files->assetUrl($a, 'poster') ?? ($a->isImage() ? $this->files->assetUrl($a, 'original') : null),
            'waveform_url' => $this->files->assetUrl($a, 'waveform'),
            'thumbs_url' => $this->files->assetUrl($a, 'thumbs'),
            'thumbs' => $a->probe['thumbs'] ?? null,
            'has_transcript' => $a->transcript_path !== null,
            'transcript_language' => $a->transcript_language,
            'style_profile' => $a->style_profile,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }

    public function job(StudioJob $j): array
    {
        return [
            'id' => $j->id,
            'type' => $j->type,
            'status' => $j->status,
            'progress' => round((float) $j->progress, 3),
            'message' => $j->message,
            'error' => $j->error,
            'project_id' => $j->project_id,
            'asset_id' => $j->asset_id,
            'result' => $j->status === StudioJob::STATUS_DONE ? $this->publicResult($j) : null,
            'attempts' => $j->attempts,
            'created_at' => $j->created_at?->toIso8601String(),
            'started_at' => $j->started_at?->toIso8601String(),
            'finished_at' => $j->finished_at?->toIso8601String(),
        ];
    }

    public function render(StudioRender $r): array
    {
        return [
            'id' => $r->id,
            'project_id' => $r->project_id,
            'quality' => $r->quality,
            'edl_revision' => $r->edl_revision,
            'duration_seconds' => $r->duration_seconds,
            'size_bytes' => $r->size_bytes,
            'width' => $r->width,
            'height' => $r->height,
            'url' => $this->files->renderUrl($r, 'video'),
            'download_url' => $this->files->renderUrl($r, 'video', null, true),
            'poster_url' => $this->files->renderUrl($r, 'poster'),
            'post_id' => $r->post_id,
            'check' => $r->check,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    public function template(StudioTemplate $t, bool $full = false): array
    {
        $previews = [];
        foreach ((array) $t->previews as $aspect => $files) {
            $previews[$aspect] = [
                'video_url' => $this->files->publicUrl($files['video'] ?? null),
                'poster_url' => $this->files->publicUrl($files['poster'] ?? null),
            ];
        }

        $out = [
            'slug' => $t->slug,
            'version' => $t->version,
            'name' => $t->name,
            'categories' => $t->categories,
            'kind' => $t->kind,
            'aspects' => $t->aspects,
            'default_duration' => $t->default_duration,
            'min_duration' => $t->min_duration,
            'max_duration' => $t->max_duration,
            'preview_url' => $this->files->publicUrl($t->preview_path),
            'poster_url' => $this->files->publicUrl($t->poster_path),
            'previews' => (object) $previews,
            'credit' => $t->credit,
            'uses_count' => $t->uses_count,
            'created_at' => $t->created_at?->toIso8601String(),
        ];
        if ($full) {
            $out['props_schema'] = $t->props_schema;
            $out['default_props'] = (object) $t->default_props;
        }

        return $out;
    }

    private function publicResult(StudioJob $j): ?array
    {
        $r = (array) ($j->result ?? []);

        return match ($j->type) {
            'auto_cut' => array_intersect_key($r, array_flip(['cut_list', 'removed_seconds', 'kept_seconds', 'revision'])),
            'render_preview', 'render_final' => array_intersect_key($r, array_flip(['render_id'])),
            'analyze_reference' => array_intersect_key($r, array_flip(['style_profile'])),
            default => null,
        };
    }

    private function firstPoster(StudioProject $p): ?string
    {
        $asset = $p->assets()->where('role', 'source')->whereNotNull('poster_path')->orderBy('id')->first();

        return $asset ? $this->files->assetUrl($asset, 'poster') : null;
    }
}
