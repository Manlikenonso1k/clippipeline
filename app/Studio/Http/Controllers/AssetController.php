<?php

namespace App\Studio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioJob;
use App\Studio\Services\AssetIntake;
use App\Studio\Services\Edl;
use App\Studio\Services\Presenter;
use App\Studio\Services\StudioFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AssetController extends Controller
{
    public function __construct(private Presenter $present) {}

    private function find(Request $request, string $id): StudioAsset
    {
        $asset = StudioAsset::query()->where('user_id', $request->user()->id)->whereKey($id)->first();
        abort_unless($asset, 404, 'Not found.');

        return $asset;
    }

    public function store(Request $request, string $projectId, AssetIntake $intake): JsonResponse
    {
        $project = ProjectController::find($request, $projectId);
        $role = $request->validate(['role' => ['required', 'in:'.implode(',', StudioAsset::ROLES)]])['role'];

        if ($request->hasFile('file') || ! $request->filled('url')) {
            $maxKb = (int) config('clippipeline.studio.max_upload_mb', 500) * 1024;
            $request->validate([
                'file' => ['required', 'file', 'mimetypes:'.implode(',', AssetIntake::MIMES[$role]), 'max:'.$maxKb],
            ], [
                'file.mimetypes' => match ($role) {
                    'source', 'reference' => 'Upload an MP4, MOV or WebM video.',
                    'music' => 'Upload an MP3, M4A or WAV file.',
                    default => 'Upload a JPG, PNG or WebP image.',
                },
            ]);
            $asset = $intake->upload($request->user(), $project, $request->file('file'), $role);
        } else {
            $url = $request->validate(['url' => ['required', 'string', 'url', 'max:2000']])['url'];
            $asset = $intake->link($request->user(), $project, $url, $role);
        }

        $project->touch();

        return response()->json($this->present->asset($asset->refresh()), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json($this->present->asset($this->find($request, $id)));
    }

    public function transcript(Request $request, string $id, StudioFiles $files): JsonResponse
    {
        $asset = $this->find($request, $id);
        if (! $asset->transcript_path || ! $files->disk()->exists($asset->transcript_path)) {
            $pending = StudioJob::query()->where('asset_id', $asset->id)->where('type', 'transcribe')
                ->whereIn('status', [StudioJob::STATUS_QUEUED, StudioJob::STATUS_RUNNING])->exists();

            return response()->json(['message' => $pending ? 'The transcript is still being made.' : 'This clip has no transcript.', 'pending' => $pending], 404);
        }
        $data = json_decode((string) $files->disk()->get($asset->transcript_path), true) ?: [];

        return response()->json([
            'asset_id' => $asset->id,
            'language' => $data['language'] ?? $asset->transcript_language,
            'engine' => $data['engine'] ?? null,
            'words' => array_map(fn ($w) => ['w' => (string) ($w['w'] ?? ''), 's' => (float) ($w['s'] ?? 0), 'e' => (float) ($w['e'] ?? 0)], (array) ($data['words'] ?? [])),
            'events' => array_values((array) ($data['events'] ?? [])),
        ]);
    }

    public function destroy(Request $request, string $id, StudioFiles $files): Response
    {
        $asset = $this->find($request, $id);
        if ($project = $asset->project) {
            $edl = Edl::withoutAsset($project->edl, $asset->id);
            if ($edl !== $project->edl) {
                $project->forceFill(['edl' => $edl, 'revision' => $project->revision + 1])->save();
            }
            if ($project->reference_asset_id === $asset->id) {
                $project->forceFill(['reference_asset_id' => null])->save();
            }
        }
        StudioJob::query()->where('asset_id', $asset->id)->whereIn('status', [StudioJob::STATUS_QUEUED, StudioJob::STATUS_RUNNING])
            ->update(['status' => StudioJob::STATUS_CANCELED, 'finished_at' => now()]);
        $files->delete(array_merge($asset->storedPaths(), [$asset->style_profile['contact_sheet_path'] ?? null]));
        $asset->delete();

        return response()->noContent();
    }
}
