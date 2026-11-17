<?php

namespace App\Studio\Services;

use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioJob;
use App\Studio\Models\StudioTemplate;

/** What POST /worker/claim returns for a job: its input plus signed URLs for every file it needs. */
class WorkerPayload
{
    public function __construct(private StudioFiles $files) {}

    public function for(StudioJob $job): array
    {
        $payload = [
            'id' => $job->id,
            'type' => $job->type,
            'attempt' => $job->attempts,
            'lease_expires_at' => $job->lease_expires_at?->toIso8601String(),
            'input' => (object) ($job->input ?? []),
            'asset' => $job->asset ? $this->asset($job->asset, true) : null,
            'project' => null,
            'assets' => (object) [],
            'limits' => [
                'max_upload_mb' => (int) config('clippipeline.studio.max_upload_mb', 500),
            ],
        ];

        if (in_array($job->type, ['render_preview', 'render_final'], true) && $job->project) {
            $edl = (array) ($job->input['edl'] ?? $job->project->edl);
            $payload['project'] = [
                'id' => $job->project->id,
                'title' => $job->project->title,
                'aspect' => $edl['aspect'] ?? $job->project->aspect,
                'fps' => $edl['fps'] ?? $job->project->fps,
                'revision' => (int) ($job->input['revision'] ?? $job->project->revision),
                'edl' => $edl,
            ];
            $assets = [];
            StudioAsset::query()->whereIn('id', Edl::assetIds($edl))->where('user_id', $job->user_id)->get()
                ->each(function (StudioAsset $a) use (&$assets) {
                    $assets[(string) $a->id] = $this->asset($a, true);
                });
            $payload['assets'] = (object) $assets;
            $payload['templates'] = $this->templateVersions($edl);
        }

        return $payload;
    }

    private function asset(StudioAsset $a, bool $withTranscript): array
    {
        return [
            'id' => $a->id,
            'role' => $a->role,
            'mime' => $a->mime,
            'sha256' => $a->sha256,
            'size_bytes' => $a->size_bytes,
            'original_name' => $a->original_name,
            'duration_seconds' => $a->duration_seconds,
            'width' => $a->width,
            'height' => $a->height,
            'has_audio' => $a->has_audio,
            'url' => $this->files->assetUrl($a, 'original'),
            'proxy_url' => $this->files->assetUrl($a, 'proxy'),
            'transcript_url' => $withTranscript ? $this->files->assetUrl($a, 'transcript') : null,
            'silence' => $a->probe['silence'] ?? null,
        ];
    }

    /** slug@version => props schema for validation on the worker. */
    private function templateVersions(array $edl): array
    {
        $wanted = [];
        foreach (array_merge($edl['main'] ?? [], $edl['overlays'] ?? []) as $seg) {
            if (isset($seg['template'])) {
                $wanted[$seg['template']] = (int) ($seg['version'] ?? 1);
            }
            if (isset($seg['transition_in']['template'])) {
                $wanted[$seg['transition_in']['template']] = 1;
            }
        }
        if (! empty($edl['captions']['enabled'])) {
            $wanted[$edl['captions']['template']] = (int) ($edl['captions']['version'] ?? 1);
        }

        $known = StudioTemplate::query()->whereIn('slug', array_keys($wanted))->get(['slug', 'version', 'is_active']);
        $out = [];
        foreach ($wanted as $slug => $version) {
            $out[] = ['slug' => $slug, 'version' => $version, 'known' => $known->contains(fn ($t) => $t->slug === $slug && $t->version === $version)];
        }

        return $out;
    }
}
