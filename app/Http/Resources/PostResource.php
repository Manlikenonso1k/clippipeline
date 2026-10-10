<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'caption' => $this->caption,
            'video_url' => $this->video_url,
            'thumbnail' => $this->thumbnail,
            'tiktok_id' => $this->tiktok_id,
            'published_at' => $this->published_at?->toIso8601String(),
            'distributions' => PostDistributionResource::collection($this->distributions),
            'analytics' => PostAnalyticResource::collection($this->analytics),
        ];
    }
}
