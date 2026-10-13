<?php

namespace App\Http\Resources;

use App\Models\PostAnalytic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PostAnalytic */
class PostAnalyticResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'platform' => $this->platform,
            'views' => (int) $this->views,
            'likes' => (int) $this->likes,
            'comments' => (int) $this->comments,
            'shares' => (int) $this->shares,
            'watch_time' => (int) $this->watch_time,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
        ];
    }
}
