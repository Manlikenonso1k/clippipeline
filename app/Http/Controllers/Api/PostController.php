<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\CrossPostService;
use App\Services\PostDeletionService;
use App\Services\PostUploadService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class PostController extends Controller
{
    private const RELATIONS = ['distributions', 'analytics'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filter' => ['nullable', 'in:all,published,processing,failed,scheduled'],
            'q' => ['nullable', 'string', 'max:200'],
            'platform' => ['nullable', 'in:'.implode(',', CrossPostService::PLATFORMS)],
        ]);
        $filter = $validated['filter'] ?? 'all';
        $search = trim((string) ($validated['q'] ?? ''));

        $posts = $request->user()->posts()
            ->with(self::RELATIONS)
            // CONTRACT §12: case-insensitive title/caption match, and platform filter.
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.str_replace(['%', '_'], '', mb_strtolower($search)).'%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(title) LIKE ?', [$like])->orWhereRaw('LOWER(caption) LIKE ?', [$like]));
            })
            ->when($validated['platform'] ?? null, fn ($q, $platform) => $q->whereHas('distributions', fn ($d) => $d->where('platform', $platform)))
            ->latest('created_at')
            ->latest('id')
            ->get();

        if ($filter !== 'all') {
            // `failed` also lists partial posts (some platforms failed) so nothing that failed is hidden.
            $match = $filter === 'failed' ? ['failed', 'partial'] : [$filter];
            $posts = $posts->filter(fn (Post $post) => in_array($post->overallStatus(), $match, true))->values();
        }

        // Plain array (no "data" wrapper) per docs/CONTRACT.md.
        return response()->json(PostResource::collection($posts)->resolve());
    }

    public function show(Request $request, string $post): JsonResponse
    {
        $model = $request->user()->posts()->with(self::RELATIONS)->whereKey($post)->first();
        abort_unless($model, 404, 'Not found.');

        return response()->json((new PostResource($model))->resolve());
    }

    public function store(Request $request, PostUploadService $uploads): JsonResponse
    {
        $user = $request->user();
        $maxKb = (int) config('clippipeline.max_upload_mb', 200) * 1024;

        $active = SocialAccount::query()
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'active'))
            ->pluck('provider')
            ->all();

        $validated = $request->validate([
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:'.$maxKb],
            'title' => ['required', 'string', 'max:100'],
            'caption' => ['nullable', 'string', 'max:2200'],
            'hashtags' => ['nullable', function (string $attribute, mixed $value, Closure $fail) {
                if (! is_string($value) && ! is_array($value)) {
                    $fail('The hashtags field must be an array or a string.');
                }
            }],
            'hashtags.*' => ['string', 'max:100'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => [
                'required',
                'string',
                'distinct',
                'in:'.implode(',', CrossPostService::PLATFORMS),
                function (string $attribute, mixed $value, Closure $fail) use ($active) {
                    if (in_array($value, CrossPostService::PLATFORMS, true) && ! in_array($value, $active, true)) {
                        $fail("Connect an active {$value} account first.");
                    }
                },
            ],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'video_duration_seconds' => ['nullable', 'numeric', 'min:0'],
            'platform_options' => ['nullable', 'array'],
        ]);

        $post = $uploads->create(
            $user,
            $request->file('video'),
            $validated['title'],
            $validated['caption'] ?? null,
            PostUploadService::normalizeHashtags($validated['hashtags'] ?? null),
            $validated['platforms'],
            ! empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at'])->utc() : null,
            (array) ($validated['platform_options'] ?? []),
            isset($validated['video_duration_seconds']) ? (float) $validated['video_duration_seconds'] : null,
        );

        return response()->json(
            (new PostResource($post->load(self::RELATIONS)))->resolve(),
            201,
        );
    }

    /** CONTRACT §12: local delete only; nothing is removed from the platforms. */
    public function destroy(Request $request, string $post, PostDeletionService $deletion): Response|JsonResponse
    {
        $model = $request->user()->posts()->whereKey($post)->first();
        if (! $model) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $deletion->delete($model);

        return response()->noContent();
    }
}
