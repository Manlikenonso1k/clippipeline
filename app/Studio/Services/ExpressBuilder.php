<?php

namespace App\Studio\Services;

use App\Studio\Models\StudioJob;
use App\Studio\Models\StudioProject;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * "Make a video" (CONTRACT §17.5): once the clips are processed and transcribed, turn the chosen
 * style pack + music + topic into a complete edit and queue the final render. No user action needed.
 *
 * Recipe statuses: waiting (clips processing) → rendering → done | failed.
 */
class ExpressBuilder
{
    /** Seconds the opening hook stays up. */
    public const HOOK_SECONDS = 3.6;

    public function __construct(
        private StyleCatalogue $catalogue,
        private AutoCutter $cutter,
        private SmartHeadlines $headlines,
        private JobQueue $jobs,
        private Usage $usage,
        private StudioFiles $files,
    ) {}

    /** Called whenever a job for the project finishes. Builds once everything it needs is ready. */
    public function maybeRun(StudioProject $project): void
    {
        $recipe = (array) ($project->recipe ?? []);
        if (($recipe['status'] ?? null) !== 'waiting') {
            return;
        }
        $sources = $project->assets()->where('role', 'source')->get();
        if ($sources->isEmpty() || $sources->contains(fn ($a) => in_array($a->status, ['pending', 'fetching', 'processing'], true))) {
            return;
        }
        $busy = StudioJob::query()->where('project_id', $project->id)
            ->whereIn('type', ['probe', 'fetch_link', 'transcribe'])
            ->whereIn('status', [StudioJob::STATUS_QUEUED, StudioJob::STATUS_RUNNING])->exists();
        if ($busy) {
            return;
        }
        if (! $sources->contains(fn ($a) => $a->status === 'ready')) {
            $this->setStatus($project, 'failed', $sources->first()?->error ?: 'The video could not be processed.');

            return;
        }

        try {
            $this->build($project);
        } catch (Throwable $e) {
            Log::warning('studio express build failed: '.$e->getMessage());
            $this->setStatus($project, 'failed', $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : 'We could not build the edit. Try another style.');
        }
    }

    public function build(StudioProject $project): void
    {
        $recipe = (array) $project->recipe;
        $style = $this->catalogue->style((string) ($recipe['style'] ?? '')) ?? $this->catalogue->styles()[0] ?? null;
        if (! $style) {
            throw ValidationException::withMessages(['style' => 'No styles are installed on the server. Run npm run templates:build in motion/.']);
        }

        $sources = $project->assets()->where('role', 'source')->where('status', 'ready')->orderBy('id')->get();
        $transcripts = [];
        foreach ($sources as $a) {
            if ($a->transcript_path && $this->files->disk()->exists($a->transcript_path)) {
                $transcripts[$a->id] = json_decode((string) $this->files->disk()->get($a->transcript_path), true) ?: null;
            }
        }

        // 1. Clips, cut on word boundaries (pauses + fillers), split for punch-ins.
        $main = [];
        foreach ($sources as $a) {
            $main[] = ['id' => Edl::newId(), 'type' => 'clip', 'asset_id' => $a->id, 'in' => 0, 'out' => round((float) $a->duration_seconds, 3), 'volume_db' => 0, 'speed' => 1, 'zoom' => 1];
        }
        $cut = $this->cutter->cut($main, $sources->keyBy('id')->all(), array_filter($transcripts), [
            'remove_silences' => true, 'silence_min_seconds' => 0.6, 'remove_fillers' => true,
            'filler_words' => config('clippipeline.studio.default_fillers'),
            'max_segment_seconds' => ! empty($style['punch_in']) ? 4.0 : null,
        ]);
        $main = $cut['main'];

        // 2. Timeline words (for headlines) mapped to the pieces that survived the cut.
        $words = [];
        $cursor = 0.0;
        foreach ($main as $seg) {
            $speed = (float) ($seg['speed'] ?? 1) ?: 1;
            foreach ((array) ($transcripts[$seg['asset_id']]['words'] ?? []) as $w) {
                $s = (float) ($w['s'] ?? 0);
                if ($s >= $seg['in'] && $s < $seg['out']) {
                    $off = ($s - $seg['in']) / $speed;
                    $text = (string) ($w['w'] ?? '');
                    foreach ((array) ($recipe['fixes'] ?? []) as $wrong => $right) {
                        $text = str_ireplace((string) $wrong, (string) $right, $text);
                    }
                    $words[] = ['w' => $text, 't' => round($cursor + $off, 3), 'anchor' => $seg['id'], 'offset' => round($off, 3)];
                }
            }
            $cursor += Edl::segmentSeconds($seg);
        }
        $clipSeconds = $cursor;

        $topic = ! empty($recipe['topic']['title']) || ! empty($recipe['topic']['hook']) ? (array) $recipe['topic'] : null;
        $picked = $this->headlines->pick($project->user, $words, $style, $topic, $recipe['platform'] ?? null);

        // 3. Overlays: hook, smart headlines (+ a money counter on "million"), styles rotating.
        $overlays = [];
        $first = $main[0]['id'];
        if (! empty($style['hook']) && trim($picked['hook']['title']) !== '') {
            // The punch word lands when it is spoken; the hook stays up until just after it.
            $punchAt = isset($picked['hook']['punch_at']) && $picked['hook']['punch'] !== '' ? (float) $picked['hook']['punch_at'] : null;
            $hookSeconds = $punchAt !== null ? min(6.0, max(self::HOOK_SECONDS, $punchAt + 1.1)) : self::HOOK_SECONDS;
            $overlays[] = ['id' => Edl::newId('o'), 'template' => $style['hook']['template'], 'version' => 1, 'anchor' => $first, 'offset' => 0,
                'duration' => round(min($hookSeconds, max(1.5, $clipSeconds - 0.5)), 2),
                'props' => ['title' => $picked['hook']['title'], 'kicker' => $picked['hook']['kicker'], 'punch' => $picked['hook']['punch'], 'punch_at' => round($punchAt ?? 1.1, 2), 'position' => 'top', 'accent' => '']];
        }
        $styles = (array) ($style['headlines']['styles'] ?? ['box']);
        $moneyDone = false;
        $hookEnd = $overlays ? (float) $overlays[0]['duration'] : 0;
        $picked['headlines'] = array_values(array_filter($picked['headlines'], fn ($h) => $words[$h['index']]['t'] >= $hookEnd + 0.2));
        foreach ($picked['headlines'] as $k => $h) {
            $w = $words[$h['index']];
            $next = $picked['headlines'][$k + 1] ?? null;
            $until = $next ? $words[$next['index']]['t'] - 0.15 : min($clipSeconds, $w['t'] + 2.2);
            $isLast = $k === count($picked['headlines']) - 1;
            $overlays[] = ['id' => Edl::newId('o'), 'template' => $style['headlines']['template'], 'version' => 1, 'anchor' => $w['anchor'], 'offset' => max(0, $w['offset'] - 0.08),
                'duration' => round(max(0.9, min(2.4, $until - $w['t'] + 0.08)), 2),
                'props' => ['text' => $h['text'], 'highlight' => $h['highlight'], 'style' => $isLast && in_array('slam', $styles, true) ? 'slam' : $styles[$k % count($styles)], 'position' => 'top', 'accent' => '']];
            if (! $moneyDone && preg_match('/million|\$|naira|cash|money/i', $h['text'])) {
                $moneyDone = true;
                $overlays[] = ['id' => Edl::newId('o'), 'template' => 'money-counter', 'version' => 1, 'anchor' => $w['anchor'], 'offset' => $w['offset'] + 0.1, 'duration' => 1.8,
                    'props' => ['value' => 1000000, 'prefix' => preg_match('/naira/i', $h['text']) ? '₦' : '$', 'label' => '', 'accent' => '']];
            }
        }

        // 4. Outro (with the creator's handle, CTA and logo), entering with the style's transition.
        if (! empty($style['outro'])) {
            $logo = $project->assets()->whereIn('role', ['logo', 'image'])->where('status', 'ready')->orderByDesc('id')->first();
            $props = (array) $style['outro']['props'];
            if ($style['outro']['template'] === 'neon-outro') {
                $props += ['title' => mb_substr((string) ($recipe['handle'] ?? '') ?: $project->title, 0, 24), 'subtitle' => '', 'items' => [], 'cta' => mb_substr((string) ($recipe['cta'] ?? '') ?: 'Follow for more', 0, 24), 'note' => mb_substr((string) ($recipe['note'] ?? ''), 0, 60)];
            } else {
                $props += ['handle' => mb_substr((string) ($recipe['handle'] ?? '') ?: '@you', 0, 40), 'cta' => mb_substr((string) ($recipe['cta'] ?? '') ?: 'Follow for more', 0, 40)];
            }
            if ($logo) {
                $props['logo'] = ['asset_id' => $logo->id];
            }
            $outro = ['id' => Edl::newId(), 'type' => 'template', 'template' => $style['outro']['template'], 'version' => 1, 'duration' => 3.8, 'slot' => 'outro', 'props' => $props];
            if (! empty($style['transition'])) {
                $outro['transition_in'] = ['template' => $style['transition'], 'duration' => 0.4];
            }
            $main[] = $outro;
        }

        // 5. Music: a library track, the creator's own upload, or none. Ducked under speech.
        $music = [];
        $choice = (string) ($recipe['music'] ?? 'style');
        if ($choice === 'style') {
            $choice = (string) ($style['music'] ?? 'none');
        }
        if ($choice === 'own') {
            $own = $project->assets()->where('role', 'music')->where('status', 'ready')->orderByDesc('id')->first();
            if ($own) {
                $music[] = ['asset_id' => $own->id, 'start' => 0, 'end' => null, 'volume' => 0.45, 'duck' => true, 'fade_in' => 0.3, 'fade_out' => 1.2];
            }
        } elseif ($choice !== 'none' && $this->catalogue->hasMusic($choice)) {
            $music[] = ['library' => $choice, 'start' => 0, 'end' => null, 'volume' => 0.45, 'duck' => true, 'fade_in' => 0.3, 'fade_out' => 1.2];
        }

        $edl = Edl::blank($project->aspect, (array) $style['theme']);
        $edl['theme'] = array_merge($edl['theme'], (array) $style['theme']);
        $edl['grade'] = $style['grade'] ?? 'none';
        $edl['sfx'] = (bool) ($style['sfx'] ?? false);
        $edl['main'] = $main;
        $edl['overlays'] = $overlays;
        $edl['captions'] = ['enabled' => $words !== [], 'template' => $style['captions']['template'], 'version' => 1, 'props' => (object) ($style['captions']['props'] ?? []), 'fixes' => (object) ($recipe['fixes'] ?? [])];
        $edl['music'] = $music;
        $edl = json_decode((string) json_encode($edl), true); // objects → arrays for validation
        Edl::validate($edl);

        $previous = $project->edl;
        $project->forceFill(['edl' => $edl, 'edl_previous' => $previous, 'revision' => $project->revision + 1])->save();

        $this->usage->assertCanRender($project->user, Edl::duration($edl), 'final');
        $job = $this->jobs->dispatch('render_final', $project->user_id, $project->id, null, ['quality' => 'final', 'revision' => $project->revision, 'edl' => $edl, 'express' => true], 3);

        $this->setStatus($project, 'rendering', null, [
            'job_id' => $job->id,
            'caption' => $picked['caption'],
            'hashtags' => $picked['hashtags'],
            'headlines' => array_map(fn ($h) => $h['text'], $picked['headlines']),
            'hook' => $picked['hook']['title'],
            'picked_by' => $picked['source'],
            'cut' => ['removed_seconds' => $cut['removed_seconds'], 'kept_seconds' => $cut['kept_seconds']],
        ]);
    }

    public function setStatus(StudioProject $project, string $status, ?string $error = null, array $extra = []): void
    {
        $recipe = array_merge((array) $project->recipe, $extra, ['status' => $status, 'error' => $error, 'updated_at' => now()->toIso8601String()]);
        $project->forceFill(['recipe' => $recipe])->save();
    }
}
