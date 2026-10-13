<?php

namespace App\Http\Resources;

use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** docs/CONTRACT.md §4 Post. @mixin Post */
class PostResource extends JsonResource
{
    private const METRICS = ['views', 'likes', 'comments', 'shares', 'saves', 'watch_time_seconds'];

    public function toArray(Request $request): array
    {
        $distributions = $this->distributions->sortBy('id')->values();
        $analytics = $this->latestAnalytics();
        $analyticsArray = PostAnalyticResource::collection($analytics)->resolve($request);

        $totals = [];
        foreach (self::METRICS as $metric) {
            $totals[$metric] = (int) array_sum(array_column($analyticsArray, $metric));
        }

        $firstPublished = $distributions
            ->filter(fn (PostDistribution $d) => $d->status === PostDistribution::STATUS_PUBLISHED && $d->published_at)
            ->min('published_at');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'caption' => $this->caption,
            'hashtags' => $this->hashtagList(),
            'video_url' => $this->video_url,
            'thumbnail' => $this->thumbnail,
            'tiktok_id' => $this->tiktok_id,
            'source' => $this->source === Post::SOURCE_UPLOAD ? Post::SOURCE_UPLOAD : Post::SOURCE_TIKTOK_INGEST,
            'status' => $this->overallStatus(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'published_at' => $firstPublished?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'distributions' => PostDistributionResource::collection($distributions)->resolve($request),
            'analytics' => $analyticsArray,
            'totals' => $totals,
        ];
    }

    /**
     * Latest snapshot per platform.
     *
     * @return Collection<int, PostAnalytic>
     */
    private function latestAnalytics(): Collection
    {
        return $this->analytics
            ->sortByDesc(fn (PostAnalytic $a) => sprintf(
                '%s|%s|%010d',
                (string) ($a->captured_on ?? ''),
                $a->recorded_at?->format('Y-m-d H:i:s') ?? '',
                $a->id,
            ))
            ->unique('platform')
            ->sortBy('platform')
            ->values();
    }
}
