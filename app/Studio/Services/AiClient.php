<?php

namespace App\Studio\Services;

use App\Models\User;
use App\Studio\Models\StudioAiCall;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Anthropic Messages client. Every call returns JSON forced through a tool
 * schema (structured output): Studio AI only ever fills props or ranges, never code.
 */
class AiClient
{
    /**
     * @param  array<int, array>  $content  user message content blocks
     * @param  array  $schema  JSON Schema of the object Claude must return
     */
    public function json(User $user, string $purpose, string $system, array $content, array $schema, int $maxTokens = 1500): array
    {
        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key' => (string) config('clippipeline.studio.anthropic_api_key'),
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => (string) config('clippipeline.studio.ai_model'),
                'max_tokens' => $maxTokens,
                'system' => $system,
                'tools' => [[
                    'name' => 'answer',
                    'description' => 'Return the answer as JSON.',
                    'input_schema' => $schema,
                ]],
                'tool_choice' => ['type' => 'tool', 'name' => 'answer'],
                'messages' => [['role' => 'user', 'content' => $content]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AI request failed ('.$response->status().').');
        }

        StudioAiCall::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'tokens_in' => (int) $response->json('usage.input_tokens', 0),
            'tokens_out' => (int) $response->json('usage.output_tokens', 0),
        ]);

        foreach ((array) $response->json('content', []) as $block) {
            if (($block['type'] ?? null) === 'tool_use') {
                return (array) ($block['input'] ?? []);
            }
        }

        throw new RuntimeException('AI returned no JSON.');
    }
}
