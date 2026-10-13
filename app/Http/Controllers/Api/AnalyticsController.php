<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** docs/CONTRACT.md §5 — returns AnalyticsService arrays unchanged. */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function overview(Request $request): JsonResponse
    {
        return response()->json($this->analytics->overview($request->user(), $this->period($request)));
    }

    public function platforms(Request $request): JsonResponse
    {
        return response()->json($this->analytics->platforms($request->user(), $this->period($request)));
    }

    public function timeseries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:7d,30d,90d'],
            'platform' => ['nullable', 'in:all,youtube,facebook,instagram,tiktok'],
        ]);

        return response()->json($this->analytics->timeseries(
            $request->user(),
            $validated['period'] ?? '7d',
            $validated['platform'] ?? 'all',
        ));
    }

    public function topPosts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:7d,30d,90d'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        return response()->json($this->analytics->topPosts(
            $request->user(),
            $validated['period'] ?? '7d',
            (int) ($validated['limit'] ?? 5),
        ));
    }

    public function post(Request $request, string $post): JsonResponse
    {
        $post = Post::query()->whereKey($post)->where('user_id', $request->user()->id)->first();
        if (! $post) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json($this->analytics->post($post));
    }

    private function period(Request $request): string
    {
        return $request->validate(['period' => ['nullable', 'in:7d,30d,90d']])['period'] ?? '7d';
    }
}
