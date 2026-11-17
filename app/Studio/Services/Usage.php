<?php

namespace App\Studio\Services;

use App\Models\User;
use App\Services\Account\MeService;
use App\Studio\Models\StudioAiCall;
use App\Studio\Models\StudioRender;
use Illuminate\Validation\ValidationException;

/** Studio plan limits (STUDIO_SPEC §4), enforced like PostUploadService::assertWithinPlan. */
class Usage
{
    /** Preview renders count at a quarter of their length. */
    public const PREVIEW_WEIGHT = 0.25;

    public function limits(User $user): array
    {
        $plan = app(MeService::class)->plan($user)['name'];
        $limits = (array) (config('clippipeline.studio.plans')[$plan] ?? config('clippipeline.studio.plans.free'));

        return ['plan' => $plan] + $limits;
    }

    public function present(User $user): array
    {
        $l = $this->limits($user);

        return [
            'plan' => $l['plan'],
            'period_start' => now()->startOfMonth()->toIso8601String(),
            'render_minutes_used' => round($this->renderMinutes($user), 2),
            'render_minutes_limit' => $l['studio_render_minutes'] ?? null,
            'ai_calls_used' => $this->aiCalls($user),
            'ai_calls_limit' => $l['studio_ai_calls'] ?? null,
            'max_source_minutes' => min((int) ($l['studio_max_source_minutes'] ?? 10), (int) config('clippipeline.studio.max_source_minutes', 10)),
            'max_upload_mb' => (int) config('clippipeline.studio.max_upload_mb', 500),
            'ai_enabled' => $this->aiEnabled(),
            'link_fetcher' => (string) config('clippipeline.studio.link_fetcher', 'off'),
        ];
    }

    public function renderMinutes(User $user): float
    {
        $seconds = 0.0;
        StudioRender::query()->where('user_id', $user->id)->where('created_at', '>=', now()->startOfMonth())
            ->get(['quality', 'duration_seconds'])
            ->each(function (StudioRender $r) use (&$seconds) {
                $seconds += (float) $r->duration_seconds * ($r->quality === 'final' ? 1 : self::PREVIEW_WEIGHT);
            });

        return $seconds / 60;
    }

    public function aiCalls(User $user): int
    {
        return StudioAiCall::query()->where('user_id', $user->id)->where('created_at', '>=', now()->startOfMonth())->count();
    }

    public function aiEnabled(): bool
    {
        return (bool) config('clippipeline.studio.ai_enabled') && (string) config('clippipeline.studio.anthropic_api_key') !== '';
    }

    /** @throws ValidationException */
    public function assertCanRender(User $user, float $seconds, string $quality): void
    {
        $limit = $this->limits($user)['studio_render_minutes'] ?? null;
        $needed = $seconds / 60 * ($quality === 'final' ? 1 : self::PREVIEW_WEIGHT);
        if ($limit !== null && $this->renderMinutes($user) + $needed > $limit) {
            throw ValidationException::withMessages(['quality' => sprintf(
                'This render needs %.1f of your %d Studio render minutes, and you have %.1f left this month.',
                $needed, $limit, max(0, $limit - $this->renderMinutes($user)),
            )]);
        }
    }

    /** @throws ValidationException */
    public function assertCanUseAi(User $user): void
    {
        if (! $this->aiEnabled()) {
            throw ValidationException::withMessages(['ai' => 'AI features are turned off on this server.']);
        }
        $limit = $this->limits($user)['studio_ai_calls'] ?? null;
        if ($limit !== null && $this->aiCalls($user) >= $limit) {
            throw ValidationException::withMessages(['ai' => "You've used all {$limit} Studio AI calls this month."]);
        }
    }

    public function maxSourceSeconds(User $user): float
    {
        return $this->present($user)['max_source_minutes'] * 60;
    }
}
