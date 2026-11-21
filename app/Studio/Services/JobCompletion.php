<?php

namespace App\Studio\Services;

use App\Services\ExpoPushService;
use App\Studio\Models\StudioJob;
use App\Studio\Models\StudioRender;
use App\Studio\Models\StudioTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Applies worker results (POST /worker/jobs/{id}/complete|fail). Idempotent. */
class JobCompletion
{
    public function __construct(private StudioFiles $files, private JobQueue $jobs) {}

    /**
     * @param  array<string, UploadedFile>  $files  keyed by name (proxy, poster, video, transcript, ...)
     */
    public function complete(StudioJob $job, array $result, array $files): StudioJob
    {
        if ($job->status === StudioJob::STATUS_DONE || $job->status === StudioJob::STATUS_CANCELED) {
            return $job;
        }

        $stored = DB::transaction(function () use ($job, $result, $files) {
            $out = match ($job->type) {
                'probe' => $this->probe($job, $result, $files),
                'fetch_link' => $this->fetched($job, $result, $files),
                'transcribe' => $this->transcribed($job, $result, $files),
                'analyze_reference' => $this->analyzed($job, $result, $files),
                'render_preview', 'render_final' => $this->rendered($job, $result, $files),
                'render_template_preview' => $this->templatePreview($job, $result, $files),
                default => $result,
            };

            $job->forceFill([
                'status' => StudioJob::STATUS_DONE,
                'progress' => 1,
                'message' => 'Done',
                'result' => $out,
                'finished_at' => now(),
                'lease_expires_at' => null,
                'error' => null,
            ])->save();

            return $out;
        });

        if (in_array($job->type, ['render_preview', 'render_final'], true) && isset($stored['render_id'])) {
            if (! empty($job->input['express']) && $job->project) {
                app(ExpressBuilder::class)->setStatus($job->project, 'done', null, ['render_id' => $stored['render_id']]);
            }
            app(StudioCleanup::class)->afterRender($job->project);
            $this->push($job, 'Your video is ready', ($job->project?->title ?: 'Studio project').' finished rendering. Tap to download or publish.', ['render_id' => $stored['render_id']]);
        }
        $this->continueExpress($job);

        return $job->refresh();
    }

    public function fail(StudioJob $job, string $error, bool $retryable): StudioJob
    {
        if ($job->isFinished()) {
            return $job;
        }

        $error = mb_substr($error, 0, 2000);
        if ($retryable && $job->attempts < $job->max_attempts) {
            $job->forceFill([
                'status' => StudioJob::STATUS_QUEUED, 'worker_id' => null, 'lease_expires_at' => null,
                'message' => 'Retrying after: '.mb_substr($error, 0, 200), 'error' => $error,
            ])->save();

            return $job;
        }

        $job->forceFill([
            'status' => StudioJob::STATUS_FAILED, 'error' => $error, 'message' => 'Failed',
            'finished_at' => now(), 'lease_expires_at' => null,
        ])->save();

        $asset = $job->asset;
        if ($asset && in_array($job->type, ['probe', 'fetch_link', 'analyze_reference'], true)) {
            $asset->forceFill(['status' => 'failed', 'error' => mb_substr($error, 0, 500)])->save();
        }
        if (in_array($job->type, ['render_preview', 'render_final'], true)) {
            if (! empty($job->input['express']) && $job->project) {
                app(ExpressBuilder::class)->setStatus($job->project, 'failed', 'Rendering failed: '.mb_substr($error, 0, 300));
            }
            $this->push($job, 'Render failed', ($job->project?->title ?: 'Studio project').': '.mb_substr($error, 0, 120));
        }
        $this->continueExpress($job);

        return $job;
    }

    /** "Make a video": build the edit as soon as clips are processed and transcribed. */
    private function continueExpress(StudioJob $job): void
    {
        if (in_array($job->type, ['probe', 'fetch_link', 'transcribe'], true) && $job->project_id) {
            $project = $job->project()->first();
            if ($project && ($project->recipe['status'] ?? null) === 'waiting') {
                app(ExpressBuilder::class)->maybeRun($project);
            }
        }
    }

    private function probe(StudioJob $job, array $r, array $files): array
    {
        $asset = $job->asset ?? throw ValidationException::withMessages(['job' => 'Asset is gone.']);
        $dir = $this->files->dir($asset->user_id, 'assets', (string) $asset->id);
        $paths = [];
        foreach (['proxy' => 'preview_proxy_path', 'poster' => 'poster_path', 'waveform' => 'waveform_path', 'thumbs' => 'thumbs_path'] as $key => $column) {
            if (isset($files[$key])) {
                $paths[$column] = $this->store($files[$key], $dir, $key);
            }
        }

        $duration = isset($r['duration']) ? (float) $r['duration'] : null;
        $asset->forceFill($paths + [
            'duration_seconds' => $duration,
            'width' => isset($r['width']) ? (int) $r['width'] : null,
            'height' => isset($r['height']) ? (int) $r['height'] : null,
            'fps' => isset($r['fps']) ? (float) $r['fps'] : null,
            'has_audio' => (bool) ($r['has_audio'] ?? false),
            'mime' => $asset->mime ?: ($r['mime'] ?? null),
            'probe' => array_intersect_key($r, array_flip(['format', 'video_codec', 'audio_codec', 'rotation', 'hdr', 'silence', 'loudness_lufs', 'thumbs', 'bitrate'])),
        ]);

        $max = app(Usage::class)->maxSourceSeconds($asset->user);
        if ($duration !== null && in_array($asset->role, ['source', 'reference'], true) && $duration > $max + 1) {
            $asset->forceFill(['status' => 'failed', 'error' => sprintf('This clip is %.1f minutes long. Your plan allows up to %d minutes per clip.', $duration / 60, $max / 60)])->save();

            return ['rejected' => 'too_long'];
        }

        $asset->forceFill(['status' => 'ready', 'error' => null])->save();
        $project = $asset->project;

        if ($asset->role === 'source') {
            if ($asset->has_audio) {
                $this->jobs->dispatch('transcribe', $asset->user_id, $asset->project_id, $asset->id, [], 3);
            }
            if ($project) {
                Edl::appendClip($project, $asset);
            }
        } elseif ($asset->role === 'reference') {
            $this->jobs->dispatch('analyze_reference', $asset->user_id, $asset->project_id, $asset->id, [
                'scene_threshold' => (float) config('clippipeline.studio.scene_threshold', 0.3),
            ], 4);
        } elseif ($asset->role === 'music' && $project && empty($project->edl['music'])) {
            $edl = $project->edl;
            $edl['music'][] = ['asset_id' => $asset->id, 'start' => 0, 'end' => null, 'volume' => 0.25, 'duck' => true, 'fade_in' => 0.5, 'fade_out' => 1.0];
            $project->forceFill(['edl' => $edl, 'revision' => $project->revision + 1])->save();
        }

        return ['asset_id' => $asset->id];
    }

    private function fetched(StudioJob $job, array $r, array $files): array
    {
        $asset = $job->asset ?? throw ValidationException::withMessages(['job' => 'Asset is gone.']);
        $file = $files['original'] ?? throw ValidationException::withMessages(['files.original' => 'The fetched file is missing.']);
        $path = $this->store($file, $this->files->dir($asset->user_id, 'assets'), (string) Str::uuid());

        $asset->forceFill([
            'path' => $path,
            'mime' => $r['mime'] ?? $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'sha256' => $r['sha256'] ?? hash_file('sha256', $this->files->disk()->path($path)),
            'original_name' => $r['name'] ?? $asset->original_name,
            'status' => 'processing',
        ])->save();

        $this->jobs->dispatch('probe', $asset->user_id, $asset->project_id, $asset->id, [], 5);

        return ['asset_id' => $asset->id];
    }

    private function transcribed(StudioJob $job, array $r, array $files): array
    {
        $asset = $job->asset ?? throw ValidationException::withMessages(['job' => 'Asset is gone.']);
        $file = $files['transcript'] ?? throw ValidationException::withMessages(['files.transcript' => 'The transcript is missing.']);
        $json = json_decode((string) file_get_contents($file->getRealPath()), true);
        if (! is_array($json) || ! isset($json['words'])) {
            throw ValidationException::withMessages(['files.transcript' => 'The transcript is not valid JSON with words.']);
        }

        $path = $this->store($file, $this->files->dir($asset->user_id, 'assets', (string) $asset->id), 'transcript', 'json');
        $asset->forceFill(['transcript_path' => $path, 'transcript_language' => mb_substr((string) ($json['language'] ?? $r['language'] ?? ''), 0, 16) ?: null])->save();

        return ['asset_id' => $asset->id, 'words' => count($json['words']), 'engine' => $json['engine'] ?? null];
    }

    private function analyzed(StudioJob $job, array $r, array $files): array
    {
        $asset = $job->asset ?? throw ValidationException::withMessages(['job' => 'Asset is gone.']);
        $profile = (array) ($r['style_profile'] ?? []);
        if (isset($files['contact_sheet'])) {
            $profile['contact_sheet_path'] = $this->store($files['contact_sheet'], $this->files->dir($asset->user_id, 'assets', (string) $asset->id), 'contact', 'jpg');
        }

        if (app(Usage::class)->aiEnabled() && isset($profile['contact_sheet_path'])) {
            try {
                $profile = app(ReferenceAi::class)->enrich($asset->user, $profile, $this->files->disk()->path($profile['contact_sheet_path']));
            } catch (Throwable $e) {
                Log::warning('studio reference AI skipped: '.$e->getMessage());
            }
        }

        // Reference media is for analysis only: delete it now, keep the profile and contact sheet.
        $this->files->delete([$asset->path, $asset->preview_proxy_path, $asset->thumbs_path, $asset->waveform_path, $asset->transcript_path]);
        $asset->forceFill([
            'style_profile' => $profile, 'status' => 'ready', 'path' => null, 'preview_proxy_path' => null,
            'thumbs_path' => null, 'waveform_path' => null, 'transcript_path' => null,
        ])->save();

        if ($project = $asset->project) {
            $project->forceFill(['style_profile' => $profile, 'reference_asset_id' => $asset->id])->save();
        }

        return ['style_profile' => $profile];
    }

    private function rendered(StudioJob $job, array $r, array $files): array
    {
        $project = $job->project ?? throw ValidationException::withMessages(['job' => 'Project is gone.']);
        $video = $files['video'] ?? throw ValidationException::withMessages(['files.video' => 'The rendered video is missing.']);
        $dir = $this->files->dir($project->user_id, 'renders');
        $name = (string) Str::uuid();

        $render = StudioRender::create([
            'project_id' => $project->id,
            'user_id' => $project->user_id,
            'job_id' => $job->id,
            'quality' => $job->type === 'render_final' ? 'final' : 'preview',
            'edl_revision' => (int) ($job->input['revision'] ?? $project->revision),
            'path' => $this->store($video, $dir, $name, 'mp4'),
            'poster_path' => isset($files['poster']) ? $this->store($files['poster'], $dir, $name, 'jpg') : null,
            'duration_seconds' => isset($r['duration']) ? (float) $r['duration'] : null,
            'size_bytes' => $video->getSize(),
            'width' => isset($r['width']) ? (int) $r['width'] : null,
            'height' => isset($r['height']) ? (int) $r['height'] : null,
            'check' => isset($r['check']) ? (array) $r['check'] : null,
            'created_at' => now(),
        ]);

        $project->forceFill(['last_render_id' => $render->id])->save();

        return ['render_id' => $render->id];
    }

    private function templatePreview(StudioJob $job, array $r, array $files): array
    {
        $in = (array) $job->input;
        $template = StudioTemplate::query()->where('slug', $in['slug'] ?? '')->where('version', (int) ($in['version'] ?? 0))->first()
            ?? throw ValidationException::withMessages(['job' => 'Template is gone.']);
        $aspect = (string) ($in['aspect'] ?? '9:16');
        $dir = "studio/templates/{$template->slug}/v{$template->version}";
        $base = str_replace(':', 'x', $aspect);

        $entry = [];
        foreach (['video' => 'mp4', 'poster' => 'jpg'] as $key => $ext) {
            if (isset($files[$key])) {
                $entry[$key] = Storage::disk('public')->putFileAs($dir, $files[$key], "{$base}.{$ext}") ?: null;
            }
        }

        $previews = (array) $template->previews;
        $previews[$aspect] = $entry;
        $update = ['previews' => $previews];
        if ($aspect === '9:16' || ! $template->preview_path) {
            $update['preview_path'] = $entry['video'] ?? $template->preview_path;
            $update['poster_path'] = $entry['poster'] ?? $template->poster_path;
        }
        $template->forceFill($update)->save();

        return ['template' => $template->slug, 'aspect' => $aspect];
    }

    private function store(UploadedFile $file, string $dir, string $name, ?string $ext = null): string
    {
        $ext ??= strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');

        return $this->files->disk()->putFileAs($dir, $file, "{$name}.{$ext}")
            ?: throw ValidationException::withMessages(['files' => 'Could not store a result file.']);
    }

    private function push(StudioJob $job, string $title, string $body, array $extra = []): void
    {
        if (! $job->user || ! $job->project_id) {
            return;
        }
        try {
            app(ExpoPushService::class)->sendToUser($job->user, $title, $body, [
                'type' => 'studio',
                'project_id' => $job->project_id,
                'url' => '/studio/'.$job->project_id,
            ] + $extra);
        } catch (Throwable $e) {
            Log::warning('studio push failed: '.$e->getMessage());
        }
    }
}
