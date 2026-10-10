<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:7d,30d,90d'],
        ]);
        $days = (int) rtrim($validated['period'] ?? '7d', 'd');

        $user = $request->user();
        $end = now()->endOfDay();
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = PostAnalytic::query()
            ->whereHas('post', fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('recorded_at', [$start, $end])
            ->get(['views', 'likes', 'comments', 'shares', 'recorded_at']);

        // One entry per day, zero-filled.
        $byDay = [];
        for ($i = 0; $i < $days; $i++) {
            $byDay[$start->copy()->addDays($i)->toDateString()] = 0;
        }
        foreach ($rows as $row) {
            $date = $row->recorded_at->toDateString();
            if (array_key_exists($date, $byDay)) {
                $byDay[$date] += (int) $row->views;
            }
        }

        $postsPublished = PostDistribution::query()
            ->where('status', PostDistribution::STATUS_PUBLISHED)
            ->whereHas('post', fn ($q) => $q->where('user_id', $user->id))
            ->where(fn ($q) => $q
                ->whereBetween('published_at', [$start, $end])
                ->orWhere(fn ($q2) => $q2->whereNull('published_at')->whereBetween('updated_at', [$start, $end])))
            ->distinct()
            ->count('post_id');

        $connected = $user->socialAccounts()
            ->where('needs_attention', false)
            ->whereNotNull('access_token')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        $trend = [];
        foreach ($byDay as $date => $views) {
            $trend[] = ['date' => $date, 'views' => $views];
        }

        return response()->json([
            'total_views' => (int) $rows->sum('views'),
            'total_likes' => (int) $rows->sum('likes'),
            'total_comments' => (int) $rows->sum('comments'),
            'total_shares' => (int) $rows->sum('shares'),
            'posts_published' => $postsPublished,
            'connected_accounts' => $connected,
            'trend' => $trend,
        ]);
    }
}
