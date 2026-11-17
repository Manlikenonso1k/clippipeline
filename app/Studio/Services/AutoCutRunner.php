<?php

namespace App\Studio\Services;

use App\Models\User;
use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioJob;
use App\Studio\Models\StudioProject;
use Illuminate\Validation\ValidationException;
use Throwable;

/** POST /studio/projects/{id}/auto-cut: runs AutoCutter inline and records it as a finished job. */
class AutoCutRunner
{
    public function __construct(private AutoCutter $cutter, private StudioFiles $files, private Usage $usage, private AiClient $ai) {}

    public function run(User $user, StudioProject $project, array $opt): StudioJob
    {
        $job = StudioJob::create([
            'type' => 'auto_cut', 'user_id' => $user->id, 'project_id' => $project->id,
            'input' => $opt, 'status' => StudioJob::STATUS_RUNNING, 'attempts' => 1, 'started_at' => now(),
        ]);

        try {
            $edl = $project->edl;
            if (! empty($opt['use_reference_style']) && empty($opt['max_segment_seconds'])) {
                $opt['max_segment_seconds'] = $project->style_profile['suggested']['max_segment_seconds'] ?? null;
            }

            $ids = array_values(array_unique(array_map(fn ($s) => (int) $s['asset_id'], array_filter($edl['main'] ?? [], fn ($s) => ($s['type'] ?? null) === 'clip'))));
            $assets = StudioAsset::query()->where('user_id', $user->id)->whereIn('id', $ids)->get()->keyBy('id')->all();
            $transcripts = [];
            foreach ($assets as $id => $a) {
                if ($a->transcript_path && $this->files->disk()->exists($a->transcript_path)) {
                    $transcripts[$id] = json_decode((string) $this->files->disk()->get($a->transcript_path), true) ?: null;
                }
            }

            $notes = [];
            if (! empty($opt['target_seconds'])) {
                if ($this->usage->aiEnabled() && $transcripts) {
                    $opt['ranges'] = $this->aiRanges($user, $edl['main'], $transcripts, (float) $opt['target_seconds']);
                } else {
                    $notes[] = 'Target length needs AI, which is off: cut by pauses and fillers only.';
                }
            }
            if (! $transcripts) {
                $notes[] = 'No transcripts yet: cut by silence only.';
            }

            $cut = $this->cutter->cut($edl['main'] ?? [], $assets, $transcripts, $opt);
            $previous = $edl;
            $edl['main'] = $cut['main'];
            $project->forceFill(['edl' => $edl, 'edl_previous' => $previous, 'revision' => $project->revision + 1])->save();

            $job->forceFill([
                'status' => StudioJob::STATUS_DONE, 'progress' => 1, 'message' => 'Done', 'finished_at' => now(),
                'result' => [
                    'cut_list' => array_merge($notes, $cut['cut_list']),
                    'removed_seconds' => $cut['removed_seconds'],
                    'kept_seconds' => $cut['kept_seconds'],
                    'revision' => $project->revision,
                ],
            ])->save();
        } catch (Throwable $e) {
            $job->forceFill(['status' => StudioJob::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()])->save();
            if ($e instanceof ValidationException) {
                throw $e;
            }
        }

        return $job->refresh();
    }

    /** Claude picks the strongest ranges (JSON ranges only). */
    private function aiRanges(User $user, array $main, array $transcripts, float $target): array
    {
        $this->usage->assertCanUseAi($user);
        $lines = [];
        foreach ($main as $seg) {
            if (($seg['type'] ?? null) !== 'clip' || ! isset($transcripts[(int) $seg['asset_id']])) {
                continue;
            }
            $phrase = [];
            foreach ((array) $transcripts[(int) $seg['asset_id']]['words'] as $w) {
                if ($w['s'] < $seg['in'] || $w['e'] > $seg['out']) {
                    continue;
                }
                $phrase[] = sprintf('[%.2f]%s', $w['s'], $w['w']);
            }
            $lines[] = 'asset '.$seg['asset_id'].': '.implode(' ', $phrase);
        }

        $answer = $this->ai->json($user, 'auto_cut',
            'You are a short-form video editor. Pick the strongest, self-contained moments from the transcript so the total is close to the target length. Ranges must start and end on word timestamps. Answer only through the tool.',
            [['type' => 'text', 'text' => "Target: {$target} seconds.\n".mb_substr(implode("\n", $lines), 0, 60000)]],
            ['type' => 'object', 'properties' => ['ranges' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => ['asset_id' => ['type' => 'integer'], 'start' => ['type' => 'number'], 'end' => ['type' => 'number']],
                'required' => ['asset_id', 'start', 'end'],
            ]]], 'required' => ['ranges']],
        );

        $byAsset = [];
        foreach ((array) ($answer['ranges'] ?? []) as $r) {
            if (isset($r['asset_id'], $r['start'], $r['end']) && $r['end'] > $r['start']) {
                $byAsset[(int) $r['asset_id']][] = [(float) $r['start'] - 0.1, (float) $r['end'] + 0.25];
            }
        }

        return $byAsset;
    }
}
