<?php

namespace App\Studio\Services;

use App\Studio\Models\StudioAsset;

/**
 * Cutting rules (STUDIO_SPEC §10). Pure PHP over cached transcripts and silence data,
 * so it runs instantly on the web host: no worker needed.
 */
class AutoCutter
{
    public const PAD_BEFORE = 0.1;   // 0.05–0.15 s before the first kept word

    public const PAD_AFTER = 0.25;   // 0.2–0.35 s after the last kept word

    public const MAX_REACTION = 1.5; // laugh/reaction tail kept after a word

    public const MIN_PIECE = 0.4;

    /**
     * @param  array<int, array>  $main  current main track
     * @param  array<int, StudioAsset>  $assets  keyed by id
     * @param  array<int, array>  $transcripts  asset id => transcript JSON ({words:[{w,s,e}], events:[]})
     * @param  array{remove_silences: bool, silence_min_seconds: float, remove_fillers: bool, filler_words: array, max_segment_seconds: ?float, ranges?: array}  $opt
     * @return array{main: array, cut_list: array<int, string>, removed_seconds: float, kept_seconds: float}
     */
    public function cut(array $main, array $assets, array $transcripts, array $opt): array
    {
        $out = [];
        $cutList = [];
        $removed = 0.0;
        $kept = 0.0;
        $zoomFlip = false;

        foreach ($main as $seg) {
            if (($seg['type'] ?? null) !== 'clip') {
                $out[] = $seg;

                continue;
            }
            $asset = $assets[(int) $seg['asset_id']] ?? null;
            $in = (float) $seg['in'];
            $segOut = (float) $seg['out'];
            $name = $asset?->original_name ?: 'clip '.$seg['asset_id'];

            $words = $this->words($transcripts[(int) $seg['asset_id']] ?? null, $in, $segOut);
            $silences = $this->silences($asset, $in, $segOut, (float) $opt['silence_min_seconds']);

            if ($words) {
                [$ranges, $reasons] = $this->rangesFromWords($words, $silences, $in, $segOut, $opt);
            } else {
                $ranges = $opt['remove_silences'] ? $this->complement([[$in, $segOut]], $silences) : [[$in, $segOut]];
                $reasons = [];
            }

            // AI-picked highlight ranges (target_seconds) narrow the result further.
            if (! empty($opt['ranges'][(int) $seg['asset_id']])) {
                $ranges = $this->intersect($ranges, $opt['ranges'][(int) $seg['asset_id']]);
            }

            if (! empty($opt['max_segment_seconds'])) {
                $ranges = $this->split($ranges, $words, (float) $opt['max_segment_seconds']);
            }

            $ranges = array_values(array_filter($ranges, fn ($r) => $r[1] - $r[0] >= self::MIN_PIECE));
            $segKept = array_sum(array_map(fn ($r) => $r[1] - $r[0], $ranges));
            $segRemoved = max(0, ($segOut - $in) - $segKept);
            $kept += $segKept;
            $removed += $segRemoved;

            $cutList[] = sprintf('%s: kept %d part%s (%.1f s), removed %.1f s%s', $name, count($ranges), count($ranges) === 1 ? '' : 's',
                $segKept, $segRemoved, $reasons ? ' ('.implode(', ', array_slice(array_unique($reasons), 0, 6)).')' : '');

            foreach ($ranges as $i => [$a, $b]) {
                $piece = $seg;
                $piece['id'] = $i === 0 ? $seg['id'] : Edl::newId();
                $piece['in'] = round($a, 3);
                $piece['out'] = round($b, 3);
                if ($i > 0) {
                    unset($piece['transition_in']);
                }
                if (! empty($opt['max_segment_seconds'])) {
                    $piece['zoom'] = $zoomFlip ? 1.08 : 1.0;
                    $zoomFlip = ! $zoomFlip;
                }
                $out[] = $piece;
            }
        }

        return ['main' => $out, 'cut_list' => $cutList, 'removed_seconds' => round($removed, 2), 'kept_seconds' => round($kept, 2)];
    }

    /** Words inside [in, out], normalised to {w, s, e, filler?}. */
    private function words(?array $transcript, float $in, float $out): array
    {
        $words = [];
        foreach ((array) ($transcript['words'] ?? []) as $w) {
            $s = (float) ($w['s'] ?? $w['start'] ?? -1);
            $e = (float) ($w['e'] ?? $w['end'] ?? -1);
            $text = trim((string) ($w['w'] ?? $w['word'] ?? $w['text'] ?? ''));
            if ($text === '' || $e <= $in || $s >= $out) {
                continue;
            }
            $words[] = ['w' => $text, 's' => max($in, $s), 'e' => min($out, $e)];
        }
        usort($words, fn ($a, $b) => $a['s'] <=> $b['s']);

        return $words;
    }

    /** Silence ranges ≥ min length inside [in, out], from the probe's silencedetect data. */
    private function silences(?StudioAsset $asset, float $in, float $out, float $min): array
    {
        $sil = (array) (($asset?->probe ?? [])['silence'] ?? []);
        $starts = array_values((array) ($sil['starts'] ?? []));
        $ends = array_values((array) ($sil['ends'] ?? []));
        $ranges = [];
        foreach ($starts as $i => $s) {
            $e = (float) ($ends[$i] ?? $out);
            $s = max($in, (float) $s);
            $e = min($out, $e);
            if ($e - $s >= $min) {
                $ranges[] = [$s, $e];
            }
        }

        return $ranges;
    }

    private function rangesFromWords(array $words, array $silences, float $in, float $out, array $opt): array
    {
        $fillers = array_map(fn ($f) => mb_strtolower(trim((string) $f)), (array) $opt['filler_words']);
        $reasons = [];
        $groups = [];
        $cur = [];

        foreach ($words as $wi => $w) {
            $w['next'] = $words[$wi + 1]['s'] ?? $out; // start of the next word, kept or not
            $norm = mb_strtolower(trim($w['w'], " \t\n\r\0\x0B.,!?;:…\"'"));
            if ($opt['remove_fillers'] && in_array($norm, $fillers, true)) {
                $reasons[] = "filler \"{$norm}\"";
                if ($cur) {
                    $groups[] = $cur;
                    $cur = [];
                }

                continue;
            }
            if ($cur && $opt['remove_silences'] && (float) $opt['silence_min_seconds'] <= $w['s'] - end($cur)['e']) {
                $reasons[] = 'pause';
                $groups[] = $cur;
                $cur = [];
            }
            $cur[] = $w;
        }
        if ($cur) {
            $groups[] = $cur;
        }

        $ranges = [];
        foreach ($groups as $gi => $g) {
            $a = max($in, $g[0]['s'] - self::PAD_BEFORE);
            $lastEnd = end($g)['e'];
            // Never run into the next word (a dropped filler included).
            $limit = min($out, end($g)['next'] - 0.02);
            $b = min($lastEnd + self::PAD_AFTER, $limit);

            // Keep a laugh/reaction: audio stays above the silence threshold after the word.
            $nextSilence = null;
            foreach ($silences as [$ss]) {
                if ($ss >= $lastEnd - 0.05) {
                    $nextSilence = $ss;
                    break;
                }
            }
            if ($nextSilence !== null && $nextSilence > $b) {
                $b = min($nextSilence + 0.1, $lastEnd + self::MAX_REACTION, $limit);
            }
            $b = max($b, $lastEnd);

            if ($ranges && $a <= end($ranges)[1] + 0.05) {
                $ranges[count($ranges) - 1][1] = max(end($ranges)[1], $b);
            } else {
                $ranges[] = [$a, $b];
            }
        }

        if (! $opt['remove_silences'] && ! $opt['remove_fillers']) {
            return [[[$in, $out]], []];
        }

        return [$ranges, $reasons];
    }

    /** Split ranges longer than $max at the word gap closest to the limit, preferring sentence ends. */
    private function split(array $ranges, array $words, float $max): array
    {
        $out = [];
        foreach ($ranges as [$a, $b]) {
            while ($b - $a > $max * 1.25) {
                $best = null;
                $bestScore = INF;
                foreach ($words as $i => $w) {
                    $next = $words[$i + 1] ?? null;
                    if (! $next || $w['e'] <= $a + 0.8 || $next['s'] >= $b - 0.8) {
                        continue;
                    }
                    $cutAt = ($w['e'] + $next['s']) / 2;
                    $score = abs(($cutAt - $a) - $max) - (preg_match('/[.!?]$/u', $w['w']) ? 0.8 : (preg_match('/[,;:]$/u', $w['w']) ? 0.4 : 0));
                    if ($cutAt - $a <= $max * 1.25 && $score < $bestScore) {
                        $best = $cutAt;
                        $bestScore = $score;
                    }
                }
                if ($best === null) {
                    break;
                }
                $out[] = [$a, $best];
                $a = $best;
            }
            $out[] = [$a, $b];
        }

        return $out;
    }

    private function complement(array $ranges, array $holes): array
    {
        foreach ($holes as [$hs, $he]) {
            $next = [];
            foreach ($ranges as [$a, $b]) {
                if ($he <= $a || $hs >= $b) {
                    $next[] = [$a, $b];

                    continue;
                }
                if ($hs > $a) {
                    $next[] = [$a, min($b, $hs + 0.15)];
                }
                if ($he < $b) {
                    $next[] = [max($a, $he - 0.1), $b];
                }
            }
            $ranges = $next;
        }

        return $ranges;
    }

    private function intersect(array $ranges, array $keep): array
    {
        $out = [];
        foreach ($ranges as [$a, $b]) {
            foreach ($keep as $k) {
                $s = max($a, (float) ($k[0] ?? $k['start'] ?? 0));
                $e = min($b, (float) ($k[1] ?? $k['end'] ?? 0));
                if ($e - $s > 0.2) {
                    $out[] = [$s, $e];
                }
            }
        }

        return $out;
    }
}
