<?php

namespace App\Http\Resources;

use App\Models\PostAnalytic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** docs/CONTRACT.md §4 PostAnalytic (latest snapshot per platform). @mixin PostAnalytic */
class PostAnalyticResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $capturedOn = $this->captured_on ?? $this->recorded_at ?? $this->created_at;

        return [
            'platform' => $this->platform,
            'distribution_id' => $this->distribution_id !== null ? (int) $this->distribution_id : null,
            'views' => (int) $this->views,
            'likes' => (int) $this->likes,
            'comments' => (int) $this->comments,
            'shares' => (int) $this->shares,
            'saves' => (int) $this->saves,
            'watch_time_seconds' => (int) ($this->watch_time_seconds ?: $this->watch_time),
            'captured_on' => $capturedOn ? substr(is_string($capturedOn) ? $capturedOn : $capturedOn->toDateString(), 0, 10) : null,
            'recorded_at' => ($this->recorded_at ?? $this->created_at)?->toIso8601String(),
        ];
    }
}
