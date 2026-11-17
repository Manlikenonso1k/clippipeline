<?php

namespace App\Studio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioRender;
use App\Studio\Services\StudioFiles;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Serves private Studio files behind temporary signed URLs (route studio.file). */
class FileController extends Controller
{
    public function show(Request $request, string $kind, int $id, string $variant, StudioFiles $files): Response
    {
        [$model, $column] = match ($kind) {
            'asset' => [StudioAsset::find($id), StudioFiles::ASSET_VARIANTS[$variant] ?? null],
            'render' => [StudioRender::find($id), StudioFiles::RENDER_VARIANTS[$variant] ?? null],
            default => [null, null],
        };
        $path = $model && $column ? $model->{$column} : null;
        abort_unless($path && $files->disk()->exists($path), 404, 'Not found.');

        $absolute = $files->disk()->path($path);
        if ($request->boolean('download')) {
            $name = $kind === 'render' ? 'clippipeline-'.$id.'.mp4' : basename($path);

            return response()->download($absolute, $name);
        }

        // BinaryFileResponse supports Range requests, so players can seek.
        return response()->file($absolute, ['Cache-Control' => 'private, max-age=3600']);
    }
}
