<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SocialAccount */
class SocialAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'handle' => $this->handle,
            'follower_count' => (int) ($this->follower_count ?? $this->meta['follower_count'] ?? 0),
            // Connected = a token exists, it is not flagged, and it has not expired.
            'connected' => ! empty($this->access_token) && ! $this->needs_attention && ! $this->isExpired(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
