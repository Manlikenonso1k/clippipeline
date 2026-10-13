<?php

namespace App\Http\Resources;

use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SocialAccount */
class SocialAccountResource extends JsonResource
{
    /** docs/CONTRACT.md §3 SocialAccount. */
    public function toArray(Request $request): array
    {
        $status = $this->effectiveStatus();

        return [
            'id' => $this->id,
            'platform' => $this->provider,
            'provider' => $this->provider,
            'connect_provider' => $this->connectProvider(),
            'handle' => $this->handle,
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_url,
            'external_id' => $this->external_id ?? ($this->meta['ig_user_id'] ?? null),
            'follower_count' => (int) ($this->follower_count ?? $this->meta['follower_count'] ?? 0),
            'status' => $status,
            'connected' => $status === SocialAccount::STATUS_ACTIVE,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'error' => $status === SocialAccount::STATUS_ACTIVE ? null : $this->last_error,
        ];
    }
}
