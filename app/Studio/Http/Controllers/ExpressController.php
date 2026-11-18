<?php

namespace App\Studio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Studio\Models\StudioProject;
use App\Studio\Models\StudioTrend;
use App\Studio\Services\Edl;
use App\Studio\Services\ExpressBuilder;
use App\Studio\Services\Presenter;
use App\Studio\Services\StyleCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** "Make a video" (CONTRACT §17.5): pick style + music + topic, upload, the server does the rest. */
class ExpressController extends Controller
{
    public function __construct(private Presenter $present, private StyleCatalogue $catalogue) {}

    private function rules(bool $create): array
    {
        $styles = array_column($this->catalogue->styles(), 'slug');
        $music = array_merge(array_column($this->catalogue->music(), 'slug'), ['none', 'own', 'style']);

        return [
            'title' => ['nullable', 'string', 'max:120'],
            'aspect' => ['nullable', 'in:'.implode(',', StudioProject::ASPECTS)],
            'style' => [$create ? 'required' : 'sometimes', Rule::in($styles)],
            'music' => ['nullable', Rule::in($music)],
            'platform' => ['nullable', 'in:'.implode(',', StudioTrend::PLATFORMS)],
            'trend_id' => ['nullable', 'integer'],
            'topic' => ['nullable', 'array'],
            'topic.title' => ['nullable', 'string', 'max:120'],
            'topic.hook' => ['nullable', 'string', 'max:24'],
            'topic.hashtags' => ['nullable', 'array', 'max:10'],
            'topic.hashtags.*' => ['string', 'max:40'],
            'handle' => ['nullable', 'string', 'max:40'],
            'cta' => ['nullable', 'string', 'max:24'],
            'note' => ['nullable', 'string', 'max:60'],
            'fixes' => ['nullable', 'array', 'max:30'],
            'fixes.*' => ['string', 'max:40'],
        ];
    }

    private function recipe(Request $request, array $v, array $base = []): array
    {
        $topic = $v['topic'] ?? ($base['topic'] ?? null);
        if (! empty($v['trend_id'])) {
            $trend = StudioTrend::query()->live()->find($v['trend_id']);
            if ($trend) {
                $topic = ['title' => $trend->title, 'hook' => $trend->hook ?: null, 'hashtags' => $trend->hashtags ?? [], 'trend_id' => $trend->id];
            }
        }

        return array_merge($base, array_filter([
            'style' => $v['style'] ?? null,
            'music' => $v['music'] ?? null,
            'platform' => $v['platform'] ?? null,
            'handle' => $v['handle'] ?? null,
            'cta' => $v['cta'] ?? null,
            'note' => $v['note'] ?? null,
            'fixes' => $v['fixes'] ?? null,
        ], fn ($x) => $x !== null), ['topic' => $topic]);
    }

    /** POST /studio/express: create the project; then upload the video to /studio/projects/{id}/assets. */
    public function store(Request $request): JsonResponse
    {
        $v = $request->validate($this->rules(true));
        $style = $this->catalogue->style($v['style']);
        $aspect = $v['aspect'] ?? '9:16';
        $project = StudioProject::create([
            'user_id' => $request->user()->id,
            'title' => trim((string) ($v['title'] ?? '')) ?: ($v['topic']['title'] ?? 'New video'),
            'aspect' => $aspect,
            'fps' => 30,
            'edl' => Edl::blank($aspect, (array) ($style['theme'] ?? [])),
            'revision' => 1,
            'last_opened_at' => now(),
            'recipe' => $this->recipe($request, $v, ['music' => 'style']) + ['status' => 'waiting', 'created_at' => now()->toIso8601String()],
        ]);

        return response()->json($this->present->project($project->refresh(), true), 201);
    }

    /** POST /studio/projects/{id}/express: change style / music / topic and make the video again. */
    public function remake(Request $request, string $id, ExpressBuilder $builder): JsonResponse
    {
        $project = ProjectController::find($request, $id);
        $v = $request->validate($this->rules(false));
        $base = (array) ($project->recipe ?? []);
        if (($base['status'] ?? null) === 'rendering') {
            return response()->json(['message' => 'This video is still being made. Try again when it is done.'], 409);
        }
        $project->forceFill(['recipe' => array_merge($this->recipe($request, $v, $base + ['music' => 'style']), ['status' => 'waiting', 'error' => null])])->save();
        $builder->maybeRun($project->refresh());

        return response()->json($this->present->project($project->refresh(), true));
    }
}
