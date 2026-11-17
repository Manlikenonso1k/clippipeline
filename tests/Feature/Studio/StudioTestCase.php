<?php

namespace Tests\Feature\Studio;

use App\Models\User;
use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioProject;
use App\Studio\Services\Edl;
use App\Studio\Services\ServiceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class StudioTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://clip.test']);
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();
        Http::fake(['exp.host/*' => Http::response(['data' => []])]);
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    protected function project(?User $user = null, array $edl = []): StudioProject
    {
        return StudioProject::create([
            'user_id' => ($user ?? $this->user)->id, 'title' => 'P', 'aspect' => '9:16', 'fps' => 30,
            'edl' => array_merge(Edl::blank('9:16'), $edl), 'revision' => 1,
        ]);
    }

    protected function readyAsset(StudioProject $project, array $attrs = []): StudioAsset
    {
        Storage::disk('local')->put("studio/{$project->user_id}/assets/a.mp4", 'x');

        return StudioAsset::create(array_merge([
            'user_id' => $project->user_id, 'project_id' => $project->id, 'role' => 'source', 'origin' => 'upload',
            'original_name' => 'clip.mp4', 'disk' => 'local', 'path' => "studio/{$project->user_id}/assets/a.mp4",
            'mime' => 'video/mp4', 'status' => 'ready', 'duration_seconds' => 10, 'has_audio' => true,
        ], $attrs));
    }

    /** Act as the render worker. */
    protected function asWorker(): void
    {
        $user = ServiceUser::get();
        $token = $user->createToken('w', ['studio:worker']);
        Sanctum::actingAs($user, ['studio:worker']);
        $this->withToken($token->plainTextToken);
    }
}
