<?php

namespace App\Studio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Studio\Models\StudioTrend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrendController extends Controller
{
    /**
     * GET /studio/trends?platform=&region= (public).
     * YouTube rows come from the YouTube Data API (studio:trends-refresh). TikTok and Instagram offer no
     * trending API to apps, so their rows are curated by an admin in /admin (source "curated").
     */
    public function index(Request $request): JsonResponse
    {
        $v = $request->validate([
            'platform' => ['nullable', 'in:'.implode(',', StudioTrend::PLATFORMS)],
            'region' => ['nullable', 'string', 'size:2'],
        ]);
        $region = strtoupper($v['region'] ?? 'NG');
        $rows = StudioTrend::query()->live()
            ->when($v['platform'] ?? null, fn ($q, $p) => $q->where('platform', $p))
            ->where(fn ($q) => $q->where('region', $region)->orWhere('region', 'GL'))
            ->orderByDesc('score')->orderByDesc('updated_at')->limit(60)->get();

        return response()->json([
            'region' => $region,
            'updated_at' => $rows->max('updated_at')?->toIso8601String(),
            'trends' => $rows->map(fn (StudioTrend $t) => [
                'id' => $t->id, 'platform' => $t->platform, 'title' => $t->title, 'hook' => $t->hook,
                'hashtags' => $t->hashtags ?? [], 'source' => $t->source, 'url' => $t->url, 'score' => $t->score,
            ])->values(),
        ]);
    }
}
