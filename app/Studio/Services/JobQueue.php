<?php

namespace App\Studio\Services;

use App\Studio\Models\StudioJob;
use Illuminate\Support\Facades\DB;

/**
 * Pull-model job queue for the render worker (STUDIO_SPEC §3, §5). Laravel never runs
 * ffmpeg/Chromium/Whisper: it only queues, leases and records jobs.
 */
class JobQueue
{
    /** Job types the worker can run (auto_cut and explainer run elsewhere). */
    public const WORKER_TYPES = ['probe', 'fetch_link', 'transcribe', 'analyze_reference', 'render_preview', 'render_final', 'render_template_preview'];

    public function dispatch(string $type, int $userId, ?int $projectId = null, ?int $assetId = null, array $input = [], int $priority = 0): StudioJob
    {
        return StudioJob::create([
            'type' => $type,
            'user_id' => $userId,
            'project_id' => $projectId,
            'asset_id' => $assetId,
            'input' => $input,
            'priority' => $priority,
            'status' => StudioJob::STATUS_QUEUED,
            'max_attempts' => 3,
        ]);
    }

    /**
     * Atomically lease the next queued job the worker can run. The conditional UPDATE
     * (status still queued) makes two workers racing for one row safe on any database.
     *
     * @param  array<int, string>  $capabilities
     */
    public function claim(string $workerId, array $capabilities): ?StudioJob
    {
        $types = array_values(array_intersect($capabilities ?: self::WORKER_TYPES, self::WORKER_TYPES));
        if (! $types) {
            return null;
        }

        for ($try = 0; $try < 5; $try++) {
            $candidate = StudioJob::query()
                ->where('status', StudioJob::STATUS_QUEUED)
                ->whereIn('type', $types)
                ->orderByDesc('priority')
                ->orderBy('created_at')
                ->first(['id']);

            if (! $candidate) {
                return null;
            }

            $claimed = StudioJob::query()
                ->whereKey($candidate->id)
                ->where('status', StudioJob::STATUS_QUEUED)
                ->update([
                    'status' => StudioJob::STATUS_RUNNING,
                    'worker_id' => $workerId,
                    'attempts' => DB::raw('attempts + 1'),
                    'lease_expires_at' => $this->leaseUntil(),
                    'started_at' => now(),
                    'progress' => 0,
                    'message' => 'Started',
                    'updated_at' => now(),
                ]);

            if ($claimed === 1) {
                return StudioJob::find($candidate->id);
            }
        }

        return null;
    }

    public function heartbeat(StudioJob $job, ?float $progress, ?string $message): StudioJob
    {
        if ($job->status !== StudioJob::STATUS_RUNNING) {
            return $job;
        }

        $job->forceFill([
            'progress' => $progress !== null ? max(0, min(1, $progress)) : $job->progress,
            'message' => $message !== null ? mb_substr($message, 0, 250) : $job->message,
            'lease_expires_at' => $this->leaseUntil(),
        ])->save();

        return $job;
    }

    /** Requeue (or fail) running jobs whose lease ran out. Returns how many were touched. */
    public function requeueStale(): int
    {
        $count = 0;
        StudioJob::query()
            ->where('status', StudioJob::STATUS_RUNNING)
            ->where('lease_expires_at', '<', now())
            ->get()
            ->each(function (StudioJob $job) use (&$count) {
                app(JobCompletion::class)->fail($job, 'Worker lease expired.', true);
                $count++;
            });

        return $count;
    }

    private function leaseUntil(): \DateTimeInterface
    {
        return now()->addMinutes((int) config('clippipeline.studio.lease_minutes', 10));
    }
}
