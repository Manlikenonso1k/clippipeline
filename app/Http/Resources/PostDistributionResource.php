<?php

namespace App\Http\Resources;

use App\Models\PostDistribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PostDistribution */
class PostDistributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'external_id' => $this->external_id,
            // The app only knows pending|processing|published|failed; 'queued' is pending.
            'status' => $this->status === 'queued' ? 'pending' : $this->status,
            'error_message' => $this->error_message ?? $this->error,
        ];
    }
}
