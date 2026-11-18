<?php

namespace App\Studio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Studio\Services\StyleCatalogue;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StyleController extends Controller
{
    /** GET /studio/styles (public): style packs + music library. */
    public function index(StyleCatalogue $catalogue): JsonResponse
    {
        return response()->json([
            'styles' => array_map(fn ($s) => [
                'slug' => $s['slug'], 'name' => $s['name'], 'description' => $s['description'],
                'theme' => $s['theme'], 'grade' => $s['grade'], 'sfx' => $s['sfx'], 'music' => $s['music'],
                'captions' => $s['captions']['template'],
            ], $catalogue->styles()),
            'music' => array_map(fn ($m) => $m + ['url' => $catalogue->musicUrl($m['slug'])], $catalogue->music()),
        ]);
    }

    /** GET /studio/music/{slug}.mp3 (public): royalty-free library track for previews. */
    public function music(string $slug, StyleCatalogue $catalogue): BinaryFileResponse
    {
        abort_unless($catalogue->hasMusic($slug), 404, 'Not found.');

        return response()->file($catalogue->musicPath($slug), ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'public, max-age=86400']);
    }
}
