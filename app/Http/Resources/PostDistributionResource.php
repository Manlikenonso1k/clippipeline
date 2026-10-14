<?php

namespace App\Http\Resources;

use App\Models\PostDistribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** docs/CONTRACT.md §4 PostDistribution. @mixin PostDistribution */
class PostDistributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = (string) $this->status;
        if ($status === 'pending') {
            $status = PostDistribution::STATUS_QUEUED; // legacy rows
        }

        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'platform' => $this->platform,
            'status' => $status,
            'external_id' => $this->external_id,
            'url' => $status === PostDistribution::STATUS_PUBLISHED ? $this->externalUrl() : null,
            'error_message' => $status === PostDistribution::STATUS_FAILED ? ($this->error_message ?? $this->error) : $this->error_message,
            'privacy' => $this->privacy,
            'attempts' => (int) $this->attempts,
            'options' => $this->options ?: null,
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
