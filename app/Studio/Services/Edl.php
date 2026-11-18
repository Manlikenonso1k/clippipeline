<?php

namespace App\Studio\Services;

use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioProject;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Project EDL v2 (STUDIO_SPEC §7): defaults, validation and small edits. */
class Edl
{
    public const DEFAULT_THEME = ['accent' => '#7C5CFF', 'accent2' => '#B14CFF', 'bg' => '#0E0E12', 'text' => '#FFFFFF', 'font' => 'Inter'];

    public static function blank(string $aspect = '9:16', array $theme = []): array
    {
        return [
            'version' => 2,
            'aspect' => $aspect,
            'fps' => 30,
            'theme' => array_merge(self::DEFAULT_THEME, array_intersect_key($theme, self::DEFAULT_THEME)),
            'fit' => 'blur',
            'main' => [],
            'overlays' => [],
            'captions' => ['enabled' => false, 'template' => 'captions-pop', 'version' => 1, 'props' => (object) [], 'fixes' => (object) []],
            'music' => [],
            'loudness' => -14,
        ];
    }

    /**
     * @throws ValidationException
     */
    public static function validate(array $edl, string $prefix = 'edl'): array
    {
        $hex = 'regex:/^#[0-9A-Fa-f]{6}$/';
        $p = $prefix.'.';
        $rules = [
            $prefix => ['required', 'array'],
            $p.'version' => ['required', 'in:2'],
            $p.'aspect' => ['required', 'in:'.implode(',', StudioProject::ASPECTS)],
            $p.'fps' => ['required', 'integer', 'in:24,25,30,60'],
            $p.'theme' => ['required', 'array'],
            $p.'theme.accent' => ['required', $hex],
            $p.'theme.accent2' => ['nullable', $hex],
            $p.'theme.bg' => ['required', $hex],
            $p.'theme.text' => ['required', $hex],
            $p.'theme.font' => ['nullable', 'string', 'max:60'],
            $p.'theme.display' => ['nullable', 'string', 'max:60'],
            $p.'grade' => ['nullable', 'in:none,vice-night,warm,punch'],
            $p.'sfx' => ['nullable', 'boolean'],
            $p.'fit' => ['required', 'in:blur,crop,black'],
            $p.'main' => ['present', 'array', 'max:300'],
            $p.'main.*.id' => ['required', 'string', 'max:40', 'distinct'],
            $p.'main.*.type' => ['required', 'in:clip,template'],
            $p.'main.*.asset_id' => ['required_if:'.$p.'main.*.type,clip', 'nullable', 'integer'],
            $p.'main.*.in' => ['required_if:'.$p.'main.*.type,clip', 'nullable', 'numeric', 'min:0'],
            $p.'main.*.out' => ['required_if:'.$p.'main.*.type,clip', 'nullable', 'numeric', 'gt:'.$p.'main.*.in'],
            $p.'main.*.volume_db' => ['nullable', 'numeric', 'between:-60,12'],
            $p.'main.*.speed' => ['nullable', 'numeric', 'between:0.25,4'],
            $p.'main.*.zoom' => ['nullable', 'numeric', 'between:1,2'],
            $p.'main.*.template' => ['required_if:'.$p.'main.*.type,template', 'nullable', 'string', 'max:64'],
            $p.'main.*.version' => ['nullable', 'integer', 'min:1'],
            $p.'main.*.duration' => ['required_if:'.$p.'main.*.type,template', 'nullable', 'numeric', 'between:0.2,60'],
            $p.'main.*.props' => ['nullable', 'array'],
            $p.'main.*.transition_in' => ['nullable', 'array'],
            $p.'main.*.transition_in.template' => ['required_with:'.$p.'main.*.transition_in', 'string', 'max:64'],
            $p.'main.*.transition_in.duration' => ['required_with:'.$p.'main.*.transition_in', 'numeric', 'between:0.1,2'],
            $p.'overlays' => ['present', 'array', 'max:100'],
            $p.'overlays.*.id' => ['required', 'string', 'max:40', 'distinct'],
            $p.'overlays.*.template' => ['required', 'string', 'max:64'],
            $p.'overlays.*.version' => ['nullable', 'integer', 'min:1'],
            $p.'overlays.*.anchor' => ['nullable', 'string', 'max:40'],
            $p.'overlays.*.offset' => ['nullable', 'numeric', 'min:0'],
            $p.'overlays.*.duration' => ['required', 'numeric', 'between:0.2,600'],
            $p.'overlays.*.props' => ['nullable', 'array'],
            $p.'captions' => ['required', 'array'],
            $p.'captions.enabled' => ['required', 'boolean'],
            $p.'captions.template' => ['required', 'string', 'max:64'],
            $p.'captions.version' => ['nullable', 'integer', 'min:1'],
            $p.'captions.props' => ['nullable', 'array'],
            $p.'captions.fixes' => ['nullable', 'array'],
            $p.'music' => ['present', 'array', 'max:10'],
            $p.'music.*.asset_id' => ['required_without:'.$p.'music.*.library', 'nullable', 'integer'],
            $p.'music.*.library' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            $p.'music.*.start' => ['nullable', 'numeric', 'min:0'],
            $p.'music.*.end' => ['nullable', 'numeric', 'min:0'],
            $p.'music.*.volume' => ['nullable', 'numeric', 'between:0,1'],
            $p.'music.*.duck' => ['nullable', 'boolean'],
            $p.'music.*.fade_in' => ['nullable', 'numeric', 'between:0,10'],
            $p.'music.*.fade_out' => ['nullable', 'numeric', 'between:0,10'],
            $p.'loudness' => ['required', 'numeric', 'between:-24,-9'],
        ];

        Validator::make([$prefix => $edl], $rules)->validate();

        return $edl;
    }

    /** Asset ids an EDL references (clips, music and asset:* template props). */
    public static function assetIds(array $edl): array
    {
        $ids = [];
        foreach ($edl['main'] ?? [] as $seg) {
            if (($seg['type'] ?? null) === 'clip' && isset($seg['asset_id'])) {
                $ids[] = (int) $seg['asset_id'];
            }
            $ids = array_merge($ids, self::propAssetIds($seg['props'] ?? []));
        }
        foreach ($edl['overlays'] ?? [] as $o) {
            $ids = array_merge($ids, self::propAssetIds($o['props'] ?? []));
        }
        foreach ($edl['music'] ?? [] as $m) {
            if (! empty($m['asset_id'])) {
                $ids[] = (int) $m['asset_id'];
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** Template props reference assets as {"asset_id": n}. */
    private static function propAssetIds(mixed $props): array
    {
        $ids = [];
        if (is_array($props)) {
            foreach ($props as $key => $value) {
                if ($key === 'asset_id' && is_numeric($value)) {
                    $ids[] = (int) $value;
                } elseif (is_array($value)) {
                    $ids = array_merge($ids, self::propAssetIds($value));
                }
            }
        }

        return $ids;
    }

    /** Output length in seconds (transitions overlap the previous segment). */
    public static function duration(array $edl): float
    {
        $total = 0.0;
        foreach (array_values($edl['main'] ?? []) as $i => $seg) {
            $total += self::segmentSeconds($seg);
            if ($i > 0 && isset($seg['transition_in']['duration'])) {
                $total -= (float) $seg['transition_in']['duration'];
            }
        }

        return round(max(0, $total), 3);
    }

    public static function segmentSeconds(array $seg): float
    {
        if (($seg['type'] ?? null) === 'clip') {
            $speed = (float) ($seg['speed'] ?? 1) ?: 1;

            return max(0, ((float) $seg['out'] - (float) $seg['in']) / $speed);
        }

        return (float) ($seg['duration'] ?? 0);
    }

    public static function newId(string $prefix = 's'): string
    {
        return $prefix.Str::lower(Str::random(8));
    }

    /** A ready source asset is appended to the main track once. */
    public static function appendClip(StudioProject $project, StudioAsset $asset): bool
    {
        $edl = $project->edl;
        foreach ($edl['main'] ?? [] as $seg) {
            if (($seg['type'] ?? null) === 'clip' && (int) ($seg['asset_id'] ?? 0) === $asset->id) {
                return false;
            }
        }
        $duration = (float) $asset->duration_seconds;
        if ($duration <= 0) {
            return false;
        }
        $clip = [
            'id' => self::newId(), 'type' => 'clip', 'asset_id' => $asset->id,
            'in' => 0, 'out' => round($duration, 3), 'volume_db' => 0, 'speed' => 1, 'zoom' => 1,
        ];
        // Clips go before the outro (segments marked "slot": "outro"), otherwise at the end.
        $main = array_values($edl['main'] ?? []);
        $at = count($main);
        foreach ($main as $i => $seg) {
            if (($seg['slot'] ?? null) === 'outro') {
                $at = $i;
                break;
            }
        }
        array_splice($main, $at, 0, [$clip]);
        $edl['main'] = $main;
        $project->forceFill(['edl' => $edl, 'revision' => $project->revision + 1])->save();

        return true;
    }

    /** Remove every reference to an asset (asset deleted). */
    public static function withoutAsset(array $edl, int $assetId): array
    {
        $edl['main'] = array_values(array_filter($edl['main'] ?? [], fn ($s) => (int) ($s['asset_id'] ?? 0) !== $assetId));
        $edl['music'] = array_values(array_filter($edl['music'] ?? [], fn ($m) => (int) ($m['asset_id'] ?? 0) !== $assetId));

        return $edl;
    }

    /**
     * Map a reference style profile onto the EDL (STUDIO_SPEC §9). Never copies reference media.
     *
     * @return array{edl: array, changes: array<int, string>}
     */
    public static function applyReference(array $edl, array $profile): array
    {
        $s = (array) ($profile['suggested'] ?? []);
        $changes = [];

        if (! empty($s['captions_template'])) {
            $edl['captions'] = array_merge($edl['captions'] ?? [], [
                'enabled' => true,
                'template' => (string) $s['captions_template'],
                'version' => (int) ($edl['captions']['version'] ?? 1),
                'props' => (object) array_merge((array) ($edl['captions']['props'] ?? []), (array) ($s['captions_props'] ?? [])),
            ]);
            $changes[] = 'Captions: '.$s['captions_template'];
        }
        if (! empty($s['accent']) && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $s['accent'])) {
            $edl['theme']['accent'] = strtoupper((string) $s['accent']);
            $changes[] = 'Accent colour: '.$edl['theme']['accent'];
        }
        if (! empty($s['punch_in'])) {
            $i = 0;
            foreach ($edl['main'] as &$seg) {
                if (($seg['type'] ?? null) === 'clip' && self::segmentSeconds($seg) >= 3) {
                    $seg['zoom'] = $i++ % 2 === 0 ? 1.0 : 1.08;
                }
            }
            unset($seg);
            $changes[] = 'Punch-in zoom on long talking segments';
        }
        if (! empty($s['max_segment_seconds'])) {
            $changes[] = 'Next auto-cut splits segments at '.$s['max_segment_seconds'].' s';
        }

        return ['edl' => $edl, 'changes' => $changes];
    }

    /** Convert a vkit EDL v1 (video-editing-/plan/edl.json) to v2. $slugToAsset maps v1 slugs to asset ids. */
    public static function fromV1(array $v1, array $slugToAsset): array
    {
        $edl = self::blank((string) ($v1['aspect'] ?? '16:9'), [
            'accent' => $v1['accent'] ?? null, 'bg' => $v1['bg_color'] ?? null,
        ]);
        $edl['theme'] = array_merge(self::DEFAULT_THEME, array_filter([
            'accent' => $v1['accent'] ?? null, 'bg' => $v1['bg_color'] ?? null,
        ]));
        $edl['loudness'] = (float) ($v1['loudness'] ?? -14);
        $edl['captions']['enabled'] = (bool) ($v1['captions'] ?? false);
        $edl['captions']['fixes'] = (object) ($v1['caption_fixes'] ?? []);
        $dip = ($v1['transition'] ?? 'cut') === 'dip';

        foreach ($v1['segments'] ?? [] as $seg) {
            $type = $seg['type'] ?? 'clip';
            $new = null;
            if ($type === 'clip' && isset($slugToAsset[$seg['slug'] ?? ''])) {
                $new = ['id' => self::newId(), 'type' => 'clip', 'asset_id' => (int) $slugToAsset[$seg['slug']],
                    'in' => (float) ($seg['in'] ?? 0), 'out' => (float) ($seg['out'] ?? 0), 'volume_db' => (float) ($seg['volume_db'] ?? 0), 'speed' => 1, 'zoom' => 1];
                if ($new['out'] <= $new['in']) {
                    continue;
                }
            } elseif ($type === 'card') {
                $lines = array_values((array) ($seg['lines'] ?? []));
                $big = (int) ($seg['big'] ?? 0);
                $new = ['id' => self::newId(), 'type' => 'template', 'template' => 'kinetic-title', 'version' => 1,
                    'duration' => (float) ($seg['seconds'] ?? 3),
                    'props' => ['kicker' => implode(' · ', array_slice($lines, 0, $big)), 'title' => $lines[$big] ?? ($lines[0] ?? ''), 'subtitle' => implode(' ', array_slice($lines, $big + 1))]];
            } elseif ($type === 'photo' && isset($slugToAsset[$seg['file'] ?? ''])) {
                $new = ['id' => self::newId(), 'type' => 'template', 'template' => 'photo-stack', 'version' => 1,
                    'duration' => (float) ($seg['seconds'] ?? 4),
                    'props' => ['photos' => [['asset_id' => (int) $slugToAsset[$seg['file']]]], 'caption' => (string) ($seg['caption'] ?? '')]];
            }
            if ($new) {
                if ($dip && $edl['main']) {
                    $new['transition_in'] = ['template' => 'flash', 'duration' => 0.4];
                }
                $edl['main'][] = $new;
            }
        }
        foreach ($v1['music'] ?? [] as $m) {
            if (isset($slugToAsset[$m['file'] ?? ''])) {
                $edl['music'][] = ['asset_id' => (int) $slugToAsset[$m['file']], 'start' => (float) ($m['start'] ?? 0), 'end' => isset($m['end']) ? (float) $m['end'] : null,
                    'volume' => (float) ($m['volume'] ?? 0.25), 'duck' => true, 'fade_in' => (float) ($m['fade_in'] ?? 0.5), 'fade_out' => (float) ($m['fade_out'] ?? 1.0)];
            }
        }

        return $edl;
    }
}
