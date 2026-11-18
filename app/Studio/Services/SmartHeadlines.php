<?php

namespace App\Studio\Services;

use App\Models\User;
use Throwable;

/**
 * Picks the moments worth a headline from the transcript ("GTA 6", "MILLIONAIRES", "BAG", "EASY").
 * Deterministic scoring by default; with STUDIO_AI_ENABLED Claude picks them (JSON only, validated).
 *
 * Words are timeline words: ['w' => text, 't' => timeline seconds, 'anchor' => segment id, 'offset' => seconds into it].
 */
class SmartHeadlines
{
    /** Never headline these (English + common Pidgin fillers). */
    private const STOP = ['the', 'a', 'an', 'and', 'or', 'but', 'so', 'of', 'to', 'in', 'on', 'at', 'for', 'with', 'is', 'are', 'was', 'were', 'be', 'it', 'its', "it's", 'that', 'this', 'these', 'those', 'i', "i'm", 'im', 'you', 'we', 'they', 'he', 'she', 'me', 'him', 'her', 'them', 'us', 'just', 'like', 'um', 'uh', 'er', 'hmm', 'know', 'yeah', 'okay', 'ok', 'right', 'all', 'do', 'does', 'did', 'have', 'has', 'had', 'can', 'will', 'would', 'should', 'could', 'there', 'here', 'then', 'than', 'very', 'really', 'some', 'any', 'over', 'out', 'up', 'down', 'about', 'how', 'what', 'when', 'where', 'who', 'why', 'which', 'if', 'not', "don't", 'dont', 'going', 'gonna', 'got', 'saying', 'say', 'said', 'thing', 'things', 'stuff', 'na', 'dey', 'una', 'wey', 'abi', 'sha', 'o', 'oh', 'bro', 'bruh', 'guys', 'one', 'also', 'too', 'my', 'your', 'our', 'their', 'from', 'into', 'by', 'as', 'be', 'been', 'being', 'am', 'let', "let's", 'lets',
        // common verbs: fine inside a phrase, never the headline word itself
        'make', 'will', 'get', 'go', 'come', 'see', 'want', 'need', 'take', 'give', 'choose', 'put', 'look', 'way', 'lot', 'line', 'own'];

    /** Words viewers react to. */
    private const POWER = ['money', 'million', 'millions', 'millionaire', 'millionaires', 'billion', 'rich', 'bag', 'cash', 'profit', 'free', 'easy', 'secret', 'viral', 'trending', 'win', 'hack', 'fast', 'best', 'new', 'now', 'today', 'download', 'app', 'ai', 'content', 'creator', 'creators', 'brand', 'views', 'followers', 'grow', 'growth', 'edit', 'post', 'publish', 'template', 'graphics', 'motion', 'naira', 'dollars', 'income', 'business', 'game', 'gaming', 'stream', 'upload', 'clip', 'clips', 'video', 'videos'];

    /** Preceding words that may join a headline phrase ("your own BAG", "GTA 6"). */
    private const JOIN = ['your', 'own', 'my', 'get', 'make', 'big', 'real', 'more', 'no', 'free', 'new', 'top', 'first', 'every', 'very', 'so', 'super', 'really', 'too'];

    /** Intensifiers: the next word is the emphasis ("very EASY"). */
    private const INTENSE = ['very', 'so', 'super', 'really', 'too', 'extremely', 'mad', 'crazy'];

    public function __construct(private AiClient $ai, private Usage $usage) {}

    public static function norm(string $w): string
    {
        return mb_strtolower(trim($w, " \t\n\r\0\x0B.,!?;:…\"'()"));
    }

    /**
     * @param  array<int, array{w: string, t: float, anchor: string, offset: float}>  $words
     * @return array{hook: array{title: string, kicker: string, punch: string}, headlines: array<int, array{index: int, text: string, highlight: string}>, caption: string, hashtags: array<int, string>, source: string}
     */
    public function pick(?User $user, array $words, array $style, ?array $topic, ?string $platform): array
    {
        if ($user && $words && $this->usage->aiEnabled()) {
            try {
                $this->usage->assertCanUseAi($user);

                return $this->fromAi($user, $words, $style, $topic, $platform) + ['source' => 'ai'];
            } catch (Throwable) {
                // fall through to the deterministic picker
            }
        }

        return $this->deterministic($words, $style, $topic, $platform) + ['source' => 'auto'];
    }

    public function deterministic(array $words, array $style, ?array $topic, ?string $platform): array
    {
        $topicTokens = $topic ? array_values(array_filter(
            array_map([self::class, 'norm'], preg_split('/\s+/', ($topic['hook'] ?? '').' '.($topic['title'] ?? '')) ?: []),
            fn ($t) => $t !== '' && ! in_array($t, self::STOP, true),
        )) : [];
        $scores = [];
        foreach ($words as $i => $w) {
            $scores[$i] = $this->score($words, $i, $topicTokens);
        }

        // Hook: the topic if one was chosen, else the strongest phrase in the first 6 seconds.
        $early = array_keys(array_filter($words, fn ($w) => $w['t'] < 6));
        $bestEarly = $this->best($early, $scores);
        $hookTitle = $topic ? mb_substr(trim((string) ($topic['hook'] ?? '')) ?: $this->short((string) ($topic['title'] ?? '')), 0, 24) : ($bestEarly !== null ? $this->phrase($words, $bestEarly) : '');
        $punch = '';
        $punchAt = null;
        foreach ($this->ranked($early, $scores) as $i) {
            $cand = $this->phrase($words, $i, 1);
            if (! str_contains(mb_strtolower($hookTitle), mb_strtolower($cand)) && $scores[$i] >= 3) {
                $punch = $cand;
                $punchAt = $words[$i]['t'];
                break;
            }
        }

        // Headlines: the best word in each window after the hook, at least 2.2 s apart.
        $every = (float) ($style['headlines']['every_seconds'] ?? 4);
        $max = (int) ($style['headlines']['max'] ?? 6);
        $end = $words ? end($words)['t'] : 0;
        $picked = [];
        $last = -INF;
        $used = array_filter([mb_strtolower($hookTitle), mb_strtolower($punch)]);
        for ($from = 4.0; $from < $end - 0.2 && count($picked) < $max; $from += $every) {
            $window = array_keys(array_filter($words, fn ($w) => $w['t'] >= $from && $w['t'] < $from + $every));
            foreach ($this->ranked($window, $scores) as $i) {
                if ($scores[$i] < 3 || $words[$i]['t'] - $last < 2.2) {
                    continue;
                }
                $text = $this->phrase($words, $i);
                $low = mb_strtolower($text);
                if (array_filter($used, fn ($u) => str_contains($u, $low) || str_contains($low, $u))) {
                    continue;
                }
                $used[] = $low;
                $picked[] = ['index' => $i, 'text' => $text, 'highlight' => self::norm($words[$i]['w'])];
                $last = $words[$i]['t'];
                break;
            }
        }

        // Closing punch: a strong word in the last 2.5 s ("very EASY") always gets the final headline.
        $tail = array_keys(array_filter($words, fn ($w) => $w['t'] >= $end - 2.5));
        $close = $this->best($tail, $scores);
        if ($close !== null && $scores[$close] >= 5 && ! in_array($close, array_column($picked, 'index'), true)) {
            $text = $this->phrase($words, $close);
            if ($picked && $words[$close]['t'] - $words[end($picked)['index']]['t'] < 1.5) {
                array_pop($picked);
            }
            if (count($picked) >= $max) {
                array_pop($picked);
            }
            $picked[] = ['index' => $close, 'text' => $text, 'highlight' => self::norm($words[$close]['w'])];
        }

        return ['hook' => ['title' => $hookTitle, 'kicker' => '', 'punch' => $punch, 'punch_at' => $punchAt], 'headlines' => $picked] + $this->caption($words, $topic, $platform, $scores);
    }

    private function score(array $words, int $i, array $topicTokens): int
    {
        $raw = $words[$i]['w'];
        $n = self::norm($raw);
        if ($n === '' || in_array($n, self::STOP, true)) {
            return 0;
        }
        $power = in_array($n, self::POWER, true);
        $s = mb_strlen($n) >= 4 || $power ? 1 : 0;
        if (preg_match('/\d|\$|₦/u', $raw)) {
            $s += 3;
        }
        $prev = $words[$i - 1]['w'] ?? '.';
        if (preg_match('/^\p{Lu}/u', $raw) && ! preg_match('/[.!?]$/', $prev)) {
            $s += 2; // proper noun mid-sentence
        }
        if ($power) {
            $s += 3;
        }
        if (in_array(self::norm($words[$i - 1]['w'] ?? ''), self::INTENSE, true)) {
            $s += 2;
        }
        if (in_array($n, $topicTokens, true)) {
            $s += 3;
        }
        if (preg_match('/[.!?]$/', $raw)) {
            $s += 1;
        }

        return $s;
    }

    private function ranked(array $idx, array $scores): array
    {
        usort($idx, fn ($a, $b) => $scores[$b] <=> $scores[$a] ?: $a <=> $b);

        return $idx;
    }

    private function best(array $idx, array $scores): ?int
    {
        return $this->ranked($idx, $scores)[0] ?? null;
    }

    /** The keyword plus up to two joining words before it ("your own bag", "GTA 6"). */
    private function phrase(array $words, int $i, int $maxBefore = 2): string
    {
        $parts = [trim($words[$i]['w'], '.,!?;:')];
        for ($k = 1; $k <= $maxBefore && isset($words[$i - $k]); $k++) {
            $p = trim($words[$i - $k]['w'], '.,!?;:');
            $n = self::norm($p);
            $ok = in_array($n, self::JOIN, true) || preg_match('/^\p{Lu}|\d/u', $p);
            if (! $ok || preg_match('/[.!?,]$/', $words[$i - $k]['w'])) {
                break;
            }
            array_unshift($parts, $p);
        }
        // A strong next word completes the phrase ("motion graphics", "ClipPipeline app").
        $next = $words[$i + 1]['w'] ?? null;
        if ($next !== null && ! preg_match('/[.!?,]$/', $words[$i]['w']) && count($parts) < 3) {
            $nn = self::norm($next);
            if (in_array($nn, self::POWER, true) && ! in_array($nn, self::STOP, true)) {
                $parts[] = trim($next, '.,!?;:');
            }
        }
        // A number right after a name belongs to it ("GTA 6").
        if (isset($words[$i + 1]) && preg_match('/^\d+$/', trim($words[$i + 1]['w'], '.,!?')) && mb_strlen(implode(' ', $parts)) < 14) {
            $parts[] = trim($words[$i + 1]['w'], '.,!?');
        }

        return mb_substr(implode(' ', $parts), 0, 28);
    }

    private function short(string $title): string
    {
        $w = preg_split('/\s+/', trim($title)) ?: [];

        return implode(' ', array_slice($w, 0, 3));
    }

    /** Post caption + hashtags for publishing. */
    private function caption(array $words, ?array $topic, ?string $platform, array $scores): array
    {
        $first = '';
        foreach ($words as $w) {
            $first .= ($first === '' ? '' : ' ').$w['w'];
            if (preg_match('/[.!?]$/', $w['w']) && mb_strlen($first) > 30) {
                break;
            }
            if (mb_strlen($first) > 110) {
                $first = rtrim(mb_substr($first, 0, mb_strrpos(mb_substr($first, 0, 110), ' ') ?: 110), ' ,').'…';
                break;
            }
        }
        $caption = trim(($topic['title'] ?? '') !== '' ? $topic['title'].' — '.$first : $first);

        $tags = array_map(fn ($h) => ltrim((string) $h, '#'), (array) ($topic['hashtags'] ?? []));
        $ranked = $this->ranked(array_keys($words), $scores);
        foreach ($ranked as $i) {
            if (count($tags) >= 6 || $scores[$i] < 3) {
                break;
            }
            $tag = preg_replace('/[^\p{L}\p{N}]/u', '', $words[$i]['w']);
            $nextTag = preg_replace('/[^\p{L}\p{N}]/u', '', $words[$i + 1]['w'] ?? '');
            if (preg_match('/^\d+$/', $nextTag) && preg_match('/^\p{Lu}+$/u', $tag)) {
                $tag .= $nextTag; // GTA 6 → GTA6
            }
            if (! preg_match('/^\p{L}[\p{L}\p{N}]{2,}$/u', $tag) || in_array(self::norm($tag), self::STOP, true)) {
                continue;
            }
            if (! in_array(mb_strtolower($tag), array_map('mb_strtolower', $tags), true)) {
                $tags[] = $tag;
            }
        }
        $tags[] = match ($platform) {
            'tiktok' => 'fyp',
            'youtube' => 'shorts',
            'instagram' => 'reels',
            default => 'viral',
        };

        return ['caption' => mb_substr($caption, 0, 300), 'hashtags' => array_values(array_unique(array_slice($tags, 0, 8)))];
    }

    private function fromAi(User $user, array $words, array $style, ?array $topic, ?string $platform): array
    {
        $numbered = [];
        foreach ($words as $i => $w) {
            $numbered[] = "{$i}:{$w['w']}";
        }
        $max = (int) ($style['headlines']['max'] ?? 6);
        $answer = $this->ai->json($user, 'express', 'You are a short-form video editor. From the numbered transcript, choose the on-screen hook and the words that deserve a big headline at the moment they are spoken. Headlines are 1-3 words, punchy, in the speaker\'s language. Also write a post caption and hashtags for the platform. Answer only through the tool.',
            [['type' => 'text', 'text' => json_encode(['platform' => $platform, 'topic' => $topic, 'max_headlines' => $max]).
                "\nTranscript (index:word):\n".mb_substr(implode(' ', $numbered), 0, 50000)]],
            ['type' => 'object', 'properties' => [
                'hook' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string', 'maxLength' => 24], 'kicker' => ['type' => 'string', 'maxLength' => 40], 'punch' => ['type' => 'string', 'maxLength' => 24]], 'required' => ['title']],
                'headlines' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['index' => ['type' => 'integer'], 'text' => ['type' => 'string', 'maxLength' => 28], 'highlight' => ['type' => 'string', 'maxLength' => 16]], 'required' => ['index', 'text']]],
                'caption' => ['type' => 'string', 'maxLength' => 300],
                'hashtags' => ['type' => 'array', 'items' => ['type' => 'string', 'maxLength' => 40]],
            ], 'required' => ['hook', 'headlines', 'caption', 'hashtags']],
            1200,
        );

        $headlines = [];
        $last = -INF;
        foreach ((array) $answer['headlines'] as $h) {
            $i = (int) ($h['index'] ?? -1);
            if (! isset($words[$i]) || $words[$i]['t'] < 3.5 || $words[$i]['t'] - $last < 2.0 || count($headlines) >= $max) {
                continue;
            }
            $headlines[] = ['index' => $i, 'text' => mb_substr((string) $h['text'], 0, 28), 'highlight' => self::norm((string) ($h['highlight'] ?? $words[$i]['w']))];
            $last = $words[$i]['t'];
        }

        return [
            'hook' => ['title' => mb_substr((string) ($answer['hook']['title'] ?? ''), 0, 24), 'kicker' => mb_substr((string) ($answer['hook']['kicker'] ?? ''), 0, 40), 'punch' => mb_substr((string) ($answer['hook']['punch'] ?? ''), 0, 24)],
            'headlines' => $headlines,
            'caption' => mb_substr((string) $answer['caption'], 0, 300),
            'hashtags' => array_values(array_slice(array_filter(array_map(fn ($t) => ltrim(trim((string) $t), '#'), (array) $answer['hashtags'])), 0, 8)),
        ];
    }
}
