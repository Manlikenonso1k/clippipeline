<?php

namespace App\Studio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioJob;
use App\Studio\Models\StudioProject;
use App\Studio\Models\StudioTemplate;
use App\Studio\Services\AutoCutRunner;
use App\Studio\Services\Edl;
use App\Studio\Services\JobQueue;
use App\Studio\Services\Presenter;
use App\Studio\Services\StudioFiles;
use App\Studio\Services\Usage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function __construct(private Presenter $present) {}

    public static function find(Request $request, string|int $id): StudioProject
    {
        $project = StudioProject::query()->where('user_id', $request->user()->id)->whereKey($id)->first();
        abort_unless($project, 404, 'Not found.');

        return $project;
    }

    public function index(Request $request): JsonResponse
    {
        $projects = StudioProject::query()->with('lastRender')->where('user_id', $request->user()->id)
            ->latest('updated_at')->latest('id')->limit(200)->get();

        return response()->json($projects->map(fn ($p) => $this->present->project($p))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'aspect' => ['nullable', 'in:'.implode(',', StudioProject::ASPECTS)],
            'theme' => ['nullable', 'array'],
            'theme.*' => ['nullable', 'string', 'max:60'],
        ]);
        $aspect = $v['aspect'] ?? '9:16';
        $theme = array_filter((array) ($v['theme'] ?? []), fn ($c, $k) => $k === 'font' || preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $c), ARRAY_FILTER_USE_BOTH);

        $project = StudioProject::create([
            'user_id' => $request->user()->id,
            'title' => trim((string) ($v['title'] ?? '')) ?: 'Untitled project',
            'aspect' => $aspect,
            'fps' => 30,
            'edl' => Edl::blank($aspect, $theme),
            'revision' => 1,
            'last_opened_at' => now(),
        ]);

        return response()->json($this->present->project($project->refresh(), true), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $project = self::find($request, $id);
        $project->forceFill(['last_opened_at' => now()])->saveQuietly();

        return response()->json($this->present->project($project, true));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $project = self::find($request, $id);
        $v = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'aspect' => ['sometimes', 'in:'.implode(',', StudioProject::ASPECTS)],
            'edl' => ['sometimes', 'array'],
            'revision' => ['required_with:edl,aspect', 'integer', 'min:1'],
        ]);

        if ((isset($v['edl']) || isset($v['aspect'])) && (int) $v['revision'] !== $project->revision) {
            return response()->json([
                'message' => 'This project was changed somewhere else. Reload to get the latest version.',
                'revision' => $project->revision,
            ], 409);
        }

        $update = [];
        if (isset($v['title'])) {
            $update['title'] = trim($v['title']) ?: $project->title;
        }
        $edl = $project->edl;
        if (isset($v['edl'])) {
            $edl = Edl::validate($v['edl']);
            $this->assertOwnsAssets($request, $edl);
        }
        if (isset($v['aspect'])) {
            $edl['aspect'] = $v['aspect'];
        }
        if (isset($v['edl']) || isset($v['aspect'])) {
            $update += ['edl' => $edl, 'aspect' => $edl['aspect'], 'fps' => (int) $edl['fps'], 'revision' => $project->revision + 1];
        }
        $project->forceFill($update)->save();

        return response()->json($this->present->project($project->refresh(), true));
    }

    public function destroy(Request $request, string $id, StudioFiles $files): Response
    {
        $project = self::find($request, $id);
        foreach ($project->assets as $asset) {
            $files->delete($asset->storedPaths());
            $files->delete([$asset->style_profile['contact_sheet_path'] ?? null]);
        }
        foreach ($project->renders as $render) {
            if (! $render->post_id) {
                $files->delete([$render->path, $render->poster_path]);
            }
        }
        $project->jobs()->whereIn('status', [StudioJob::STATUS_QUEUED, StudioJob::STATUS_RUNNING])->update(['status' => StudioJob::STATUS_CANCELED]);
        $project->delete();

        return response()->noContent();
    }

    public function autoCut(Request $request, string $id, AutoCutRunner $runner): JsonResponse
    {
        $project = self::find($request, $id);
        $v = $request->validate([
            'remove_silences' => ['sometimes', 'boolean'],
            'silence_min_seconds' => ['sometimes', 'numeric', 'between:0.2,5'],
            'silence_db' => ['sometimes', 'numeric', 'between:-70,-10'],
            'remove_fillers' => ['sometimes', 'boolean'],
            'filler_words' => ['sometimes', 'array', 'max:50'],
            'filler_words.*' => ['string', 'max:30'],
            'max_segment_seconds' => ['nullable', 'numeric', 'between:0.8,60'],
            'use_reference_style' => ['sometimes', 'boolean'],
            'target_seconds' => ['nullable', 'numeric', 'between:5,600'],
        ]);

        if (! collect($project->edl['main'] ?? [])->contains(fn ($s) => ($s['type'] ?? null) === 'clip')) {
            throw ValidationException::withMessages(['project' => 'Add at least one clip before auto-cut.']);
        }

        $job = $runner->run($request->user(), $project, [
            'remove_silences' => (bool) ($v['remove_silences'] ?? true),
            'silence_min_seconds' => (float) ($v['silence_min_seconds'] ?? 0.6),
            'silence_db' => (float) ($v['silence_db'] ?? -35),
            'remove_fillers' => (bool) ($v['remove_fillers'] ?? true),
            'filler_words' => $v['filler_words'] ?? config('clippipeline.studio.default_fillers'),
            'max_segment_seconds' => isset($v['max_segment_seconds']) ? (float) $v['max_segment_seconds'] : null,
            'use_reference_style' => (bool) ($v['use_reference_style'] ?? false),
            'target_seconds' => isset($v['target_seconds']) ? (float) $v['target_seconds'] : null,
        ]);

        return response()->json($this->present->job($job), 202);
    }

    public function undoAutoCut(Request $request, string $id): JsonResponse
    {
        $project = self::find($request, $id);
        if (! $project->edl_previous) {
            throw ValidationException::withMessages(['project' => 'There is no auto-cut to undo.']);
        }
        $project->forceFill(['edl' => $project->edl_previous, 'edl_previous' => null, 'revision' => $project->revision + 1])->save();

        return response()->json($this->present->project($project->refresh(), true));
    }

    public function applyReference(Request $request, string $id): JsonResponse
    {
        $project = self::find($request, $id);
        if (! $project->style_profile) {
            throw ValidationException::withMessages(['project' => 'Add a reference video and wait for its analysis first.']);
        }
        $applied = Edl::applyReference($project->edl, $project->style_profile);
        $project->forceFill(['edl' => $applied['edl'], 'revision' => $project->revision + 1])->save();

        return response()->json(['changes' => $applied['changes']] + $this->present->project($project->refresh(), true));
    }

    public function render(Request $request, string $id, JobQueue $jobs, Usage $usage): JsonResponse
    {
        $project = self::find($request, $id);
        $v = $request->validate(['quality' => ['required', 'in:preview,final']]);
        $type = 'render_'.$v['quality'];
        $edl = $project->edl;

        if (empty($edl['main'])) {
            throw ValidationException::withMessages(['project' => 'Add a clip or template before rendering.']);
        }

        $ids = Edl::assetIds($edl);
        $assets = StudioAsset::query()->where('user_id', $request->user()->id)->whereIn('id', $ids)->get();
        if ($assets->count() !== count($ids)) {
            throw ValidationException::withMessages(['project' => 'Some media in this edit was deleted. Remove it from the timeline and try again.']);
        }
        $notReady = $assets->first(fn ($a) => $a->status !== 'ready' || ! $a->path);
        if ($notReady) {
            throw ValidationException::withMessages(['project' => "\"{$notReady->original_name}\" is still processing. Try again when it is ready."]);
        }

        $existing = $project->jobs()->where('type', $type)->whereIn('status', [StudioJob::STATUS_QUEUED, StudioJob::STATUS_RUNNING])
            ->where('input->revision', $project->revision)->first();
        if ($existing) {
            return response()->json($this->present->job($existing), 202);
        }

        $usage->assertCanRender($request->user(), Edl::duration($edl), $v['quality']);

        $job = $jobs->dispatch($type, $request->user()->id, $project->id, null, [
            'quality' => $v['quality'],
            'revision' => $project->revision,
            'edl' => $edl,
        ], $v['quality'] === 'preview' ? 4 : 2);

        if ($v['quality'] === 'final') {
            foreach (array_merge($edl['main'], $edl['overlays'] ?? []) as $seg) {
                if (isset($seg['template'])) {
                    StudioTemplate::query()->where('slug', $seg['template'])->where('version', (int) ($seg['version'] ?? 1))->increment('uses_count');
                }
            }
        }

        return response()->json($this->present->job($job), 202);
    }

    public function renders(Request $request, string $id): JsonResponse
    {
        $project = self::find($request, $id);

        return response()->json($project->renders()->latest('created_at')->latest('id')->get()->map(fn ($r) => $this->present->render($r))->all());
    }

    private function assertOwnsAssets(Request $request, array $edl): void
    {
        $ids = Edl::assetIds($edl);
        $owned = StudioAsset::query()->where('user_id', $request->user()->id)->whereIn('id', $ids)->count();
        if ($owned !== count($ids)) {
            throw ValidationException::withMessages(['edl' => 'The edit references media that is not in your library.']);
        }
    }
}
