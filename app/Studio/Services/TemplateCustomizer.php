<?php

namespace App\Studio\Services;

use App\Models\User;
use App\Studio\Models\StudioTemplate;

/**
 * "Customize" (STUDIO_SPEC §8). Deterministic by default: colours and text map onto the
 * template's props. With STUDIO_AI_ENABLED, Claude fills props (JSON only) and the result
 * is validated against the template's own schema subset.
 */
class TemplateCustomizer
{
    public function __construct(private AiClient $ai, private Usage $usage) {}

    /** @return array{props: array, source: string} */
    public function customize(User $user, StudioTemplate $t, array $req): array
    {
        $props = $this->deterministic($t, $req);
        $source = 'theme';

        if ($this->usage->aiEnabled() && trim((string) ($req['purpose'] ?? '')) !== '') {
            $this->usage->assertCanUseAi($user);
            $answer = $this->ai->json($user, 'customize',
                'You fill the props of a motion-graphics template for a short social video. Answer only through the tool with props that match the schema. Keep text short, punchy and in the language of the purpose. Never write code.',
                [['type' => 'text', 'text' => json_encode([
                    'template' => $t->name, 'purpose' => $req['purpose'] ?? '', 'aspect' => $req['aspect'] ?? '9:16',
                    'colors' => $req['colors'] ?? [], 'text' => $req['text'] ?? null, 'defaults' => $t->default_props,
                ])]],
                $this->toolSchema((array) $t->props_schema),
                800,
            );
            $props = array_merge($props, $this->clean((array) $t->props_schema, $answer));
            $source = 'ai';
        }

        return ['props' => $props, 'source' => $source];
    }

    private function deterministic(StudioTemplate $t, array $req): array
    {
        $schema = (array) $t->props_schema;
        $props = (array) $t->default_props;
        $colors = array_values(array_filter((array) ($req['colors'] ?? []), fn ($c) => is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c)));
        $ci = 0;
        $text = trim((string) ($req['text'] ?? ''));

        foreach ((array) ($schema['properties'] ?? []) as $key => $def) {
            $isColor = ($def['format'] ?? null) === 'color';
            if ($isColor && isset($colors[$ci])) {
                $props[$key] = strtoupper($colors[$ci++]);
            } elseif ($text !== '' && ($def['type'] ?? null) === 'string' && ! $isColor && ! isset($def['enum']) && in_array($key, ['title', 'text', 'headline', 'name'], true)) {
                $props[$key] = mb_substr($text, 0, (int) ($def['maxLength'] ?? 80));
                $text = '';
            }
        }

        return $props;
    }

    /** Keep only keys in the schema whose values satisfy the supported subset. */
    public function clean(array $schema, array $props): array
    {
        $out = [];
        foreach ((array) ($schema['properties'] ?? []) as $key => $def) {
            if (! array_key_exists($key, $props)) {
                continue;
            }
            $v = $props[$key];
            $type = $def['type'] ?? null;
            $ok = match (true) {
                isset($def['enum']) => in_array($v, $def['enum'], true),
                ($def['format'] ?? null) === 'color' => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v),
                $type === 'string' => is_string($v) && mb_strlen($v) <= (int) ($def['maxLength'] ?? 500),
                $type === 'number', $type === 'integer' => is_numeric($v) && (! isset($def['minimum']) || $v >= $def['minimum']) && (! isset($def['maximum']) || $v <= $def['maximum']),
                $type === 'boolean' => is_bool($v),
                $type === 'array' => is_array($v) && array_is_list($v) && count($v) <= (int) ($def['maxItems'] ?? 20) && ! array_filter($v, fn ($x) => ! is_string($x) && ! is_array($x)),
                default => false,
            };
            if ($ok) {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    /** The tool schema drops asset pickers: AI never chooses media. */
    private function toolSchema(array $schema): array
    {
        $props = [];
        foreach ((array) ($schema['properties'] ?? []) as $key => $def) {
            if (str_starts_with((string) ($def['format'] ?? ''), 'asset:')) {
                continue;
            }
            unset($def['format']);
            $props[$key] = $def;
        }

        return ['type' => 'object', 'properties' => (object) $props];
    }
}
