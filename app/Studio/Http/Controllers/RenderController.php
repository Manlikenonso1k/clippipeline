<?php

namespace App\Studio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\SocialAccount;
use App\Services\CrossPostService;
use App\Services\PostUploadService;
use App\Studio\Models\StudioRender;
use App\Studio\Services\StudioFiles;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class RenderController extends Controller
{
    private function find(Request $request, string $id): StudioRender
    {
        $render = StudioRender::query()->where('user_id', $request->user()->id)->whereKey($id)->first();
        abort_unless($render, 404, 'Not found.');

        return $render;
    }

    /** Same fields as POST /posts minus `video` (CONTRACT §17). */
    public function crossPost(Request $request, string $id, PostUploadService $uploads, StudioFiles $files): JsonResponse
    {
        $render = $this->find($request, $id);
        $user = $request->user();
        $active = SocialAccount::query()->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'active'))->pluck('provider')->all();

        $v = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'caption' => ['nullable', 'string', 'max:2200'],
            'hashtags' => ['nullable', function (string $attribute, mixed $value, Closure $fail) {
                if (! is_string($value) && ! is_array($value)) {
                    $fail('The hashtags field must be an array or a string.');
                }
            }],
            'hashtags.*' => ['string', 'max:100'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['required', 'string', 'distinct', 'in:'.implode(',', CrossPostService::PLATFORMS),
                function (string $attribute, mixed $value, Closure $fail) use ($active) {
                    if (in_array($value, CrossPostService::PLATFORMS, true) && ! in_array($value, $active, true)) {
                        $fail("Connect an active {$value} account first.");
                    }
                },
            ],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'platform_options' => ['nullable', 'array'],
        ]);

        $post = $uploads->createFromStoredFile(
            $user,
            $files->disk()->path($render->path),
            $v['title'],
            $v['caption'] ?? null,
            PostUploadService::normalizeHashtags($v['hashtags'] ?? null),
            $v['platforms'],
            ! empty($v['scheduled_at']) ? Carbon::parse($v['scheduled_at'])->utc() : null,
            (array) ($v['platform_options'] ?? []),
        );
        $render->forceFill(['post_id' => $post->id])->save();

        return response()->json((new PostResource($post->load(['distributions', 'analytics'])))->resolve(), 201);
    }

    public function destroy(Request $request, string $id, StudioFiles $files): Response
    {
        $render = $this->find($request, $id);
        $files->delete([$render->path, $render->poster_path]);
        if ($render->project && $render->project->last_render_id === $render->id) {
            $render->project->forceFill(['last_render_id' => null])->save();
        }
        $render->delete();

        return response()->noContent();
    }
}
