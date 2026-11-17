<?php

namespace App\Studio\Services;

use App\Models\User;
use App\Studio\Models\StudioTemplate;

/** Optional Claude-vision notes on a reference contact sheet (STUDIO_SPEC §9). JSON only. */
class ReferenceAi
{
    public function __construct(private AiClient $ai, private Usage $usage) {}

    public function enrich(User $user, array $profile, string $contactSheetPath): array
    {
        $this->usage->assertCanUseAi($user);
        $captionTemplates = StudioTemplate::query()->where('kind', 'captions')->where('is_active', true)->pluck('slug')->unique()->values()->all() ?: ['captions-pop', 'captions-clean'];

        $answer = $this->ai->json($user, 'analyze_reference',
            'You describe the editing style of a short video from a contact sheet of its frames, so an editor can imitate the style (never the content). Answer only through the tool.',
            [
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => base64_encode((string) file_get_contents($contactSheetPath))]],
                ['type' => 'text', 'text' => 'Measured facts: '.json_encode(array_intersect_key($profile, array_flip(['duration', 'shot_count', 'avg_shot_seconds', 'pace', 'palette', 'has_speech', 'has_music'])))
                    .'. Caption templates you may suggest: '.implode(', ', $captionTemplates).'.'],
            ],
            [
                'type' => 'object',
                'properties' => [
                    'caption_style' => ['type' => 'string', 'maxLength' => 200],
                    'text_overlays' => ['type' => 'string', 'maxLength' => 200],
                    'transitions' => ['type' => 'string', 'maxLength' => 200],
                    'captions_template' => ['type' => 'string', 'enum' => $captionTemplates],
                    'accent' => ['type' => 'string', 'pattern' => '^#[0-9A-Fa-f]{6}$'],
                    'punch_in' => ['type' => 'boolean'],
                ],
                'required' => ['caption_style', 'text_overlays', 'transitions'],
            ],
            600,
        );

        $profile['ai_notes'] = array_intersect_key($answer, array_flip(['caption_style', 'text_overlays', 'transitions']));
        $profile['suggested'] = array_merge((array) ($profile['suggested'] ?? []), array_filter(
            array_intersect_key($answer, array_flip(['captions_template', 'accent', 'punch_in'])),
            fn ($v) => $v !== null && $v !== '',
        ));

        return $profile;
    }
}
