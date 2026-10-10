<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filter' => ['nullable', 'in:all,published,processing,failed'],
        ]);
        $filter = $validated['filter'] ?? 'all';

        $posts = $request->user()->posts()
            ->with(['distributions', 'analytics'])
            ->when($filter !== 'all', fn ($q) => $q->whereHas(
                'distributions',
                fn ($d) => $d->where('status', $filter)
            ))
            ->latest('id')
            ->get();

        // Plain array (no "data" wrapper) to match the mobile app's types.
        return response()->json(PostResource::collection($posts)->resolve());
    }

    public function show(Request $request, Post $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 404);

        return response()->json(
            (new PostResource($post->load(['distributions', 'analytics'])))->resolve()
        );
    }
}
