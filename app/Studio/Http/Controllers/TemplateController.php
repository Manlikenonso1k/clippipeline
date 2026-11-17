<?php

namespace App\Studio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Studio\Models\StudioProject;
use App\Studio\Models\StudioTemplate;
use App\Studio\Services\Presenter;
use App\Studio\Services\TemplateCustomizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TemplateController extends Controller
{
    public function __construct(private Presenter $present) {}

    /** Latest active version of every template. */
    private function latest(): Collection
    {
        return StudioTemplate::query()->where('is_active', true)->orderBy('sort')->orderBy('slug')->orderByDesc('version')->get()
            ->unique('slug')->values();
    }

    public function index(Request $request): JsonResponse
    {
        $v = $request->validate([
            'category' => ['nullable', 'string', 'max:40'],
            'kind' => ['nullable', 'in:'.implode(',', StudioTemplate::KINDS)],
            'aspect' => ['nullable', 'in:'.implode(',', StudioProject::ASPECTS)],
            'sort' => ['nullable', 'in:popular,new'],
        ]);
        $all = $this->latest();

        $categories = collect(StudioTemplate::CATEGORIES)
            ->map(fn ($c) => ['name' => $c, 'count' => $all->filter(fn ($t) => in_array($c, (array) $t->categories, true))->count()])
            ->filter(fn ($c) => $c['count'] > 0)->values();

        $list = $all
            ->when($v['category'] ?? null, fn ($c, $cat) => $c->filter(fn ($t) => in_array($cat, (array) $t->categories, true)))
            ->when($v['kind'] ?? null, fn ($c, $kind) => $c->where('kind', $kind))
            ->when($v['aspect'] ?? null, fn ($c, $aspect) => $c->filter(fn ($t) => in_array($aspect, (array) $t->aspects, true)));
        $list = match ($v['sort'] ?? 'popular') {
            'new' => $list->sortByDesc('created_at'),
            default => $list->sortByDesc('uses_count'),
        };

        return response()->json([
            'total' => $all->count(),
            'categories' => $categories,
            'templates' => $list->values()->map(fn ($t) => $this->present->template($t, true))->all(),
        ]);
    }

    public function show(string $slug, Request $request): JsonResponse
    {
        $query = StudioTemplate::query()->where('slug', $slug);
        $template = $request->filled('version')
            ? $query->where('version', (int) $request->query('version'))->first()
            : $query->where('is_active', true)->orderByDesc('version')->first();
        abort_unless($template, 404, 'Not found.');

        return response()->json($this->present->template($template, true));
    }

    public function customize(Request $request, string $slug, TemplateCustomizer $customizer): JsonResponse
    {
        $template = StudioTemplate::query()->where('slug', $slug)->where('is_active', true)->orderByDesc('version')->first();
        abort_unless($template, 404, 'Not found.');
        $v = $request->validate([
            'purpose' => ['nullable', 'string', 'max:300'],
            'aspect' => ['nullable', 'in:'.implode(',', StudioProject::ASPECTS)],
            'colors' => ['nullable', 'array', 'max:6'],
            'colors.*' => ['string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text' => ['nullable', 'string', 'max:200'],
        ]);

        return response()->json($customizer->customize($request->user(), $template, $v) + ['slug' => $template->slug, 'version' => $template->version]);
    }
}
