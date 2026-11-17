<?php

namespace App\Studio\Console;

use App\Models\User;
use App\Studio\Models\StudioJob;
use App\Studio\Models\StudioProject;
use App\Studio\Models\StudioRender;
use App\Studio\Services\AssetIntake;
use App\Studio\Services\AutoCutRunner;
use App\Studio\Services\Edl;
use App\Studio\Services\JobQueue;
use App\Studio\Services\StudioFiles;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

/** End-to-end check (needs a running render worker): upload → probe → transcribe → auto-cut → preview render. */
class Smoke extends Command
{
    protected $signature = 'studio:smoke {email} {video} {--timeout=900} {--quality=preview} {--title=Studio smoke test}';

    protected $description = 'Create a Studio project from a local video, auto-cut it and render it through the worker.';

    public function handle(AssetIntake $intake, AutoCutRunner $autoCut, JobQueue $jobs, StudioFiles $files): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        $path = (string) $this->argument('video');
        if (! $user || ! is_file($path)) {
            $this->error(! $user ? 'No user with that email.' : 'Video file not found.');

            return self::FAILURE;
        }

        $started = microtime(true);
        $project = StudioProject::create([
            'user_id' => $user->id, 'title' => (string) $this->option('title'), 'aspect' => '9:16', 'fps' => 30,
            'edl' => Edl::blank('9:16'), 'revision' => 1, 'last_opened_at' => now(),
        ]);
        $edl = $project->edl;
        $edl['captions']['enabled'] = true;
        $edl['main'][] = ['id' => Edl::newId(), 'type' => 'template', 'template' => 'kinetic-title', 'version' => 1, 'duration' => 2.5,
            'props' => ['kicker' => 'ClipPipeline', 'title' => 'Studio smoke test', 'subtitle' => now()->toFormattedDateString()]];
        $project->forceFill(['edl' => $edl])->save();
        $this->line("Project {$project->id} created.");

        $asset = $intake->upload($user, $project, new UploadedFile($path, basename($path), null, null, true), 'source');
        $this->line("Asset {$asset->id} uploaded, waiting for probe + transcript...");

        $deadline = time() + (int) $this->option('timeout');
        if (! $this->waitFor(fn () => $asset->refresh()->status === 'ready' || $asset->status === 'failed', $deadline)) {
            return $this->fail2('Timed out waiting for the probe. Is the worker running?');
        }
        if ($asset->status === 'failed') {
            return $this->fail2('Probe failed: '.$asset->error);
        }
        $this->waitFor(fn () => ! StudioJob::query()->where('asset_id', $asset->id)->where('type', 'transcribe')
            ->whereIn('status', ['queued', 'running'])->exists(), $deadline);

        $cut = $autoCut->run($user, $project->refresh(), [
            'remove_silences' => true, 'silence_min_seconds' => 0.6, 'silence_db' => -35, 'remove_fillers' => true,
            'filler_words' => config('clippipeline.studio.default_fillers'), 'max_segment_seconds' => null,
            'use_reference_style' => false, 'target_seconds' => null,
        ]);
        foreach ((array) ($cut->result['cut_list'] ?? [$cut->error]) as $line) {
            $this->line('  cut: '.$line);
        }

        $project->refresh();
        $quality = $this->option('quality') === 'final' ? 'final' : 'preview';
        $job = $jobs->dispatch('render_'.$quality, $user->id, $project->id, null, ['quality' => $quality, 'revision' => $project->revision, 'edl' => $project->edl], 9);
        $this->line("Render job {$job->id} queued...");
        $renderStart = microtime(true);
        if (! $this->waitFor(fn () => $job->refresh()->isFinished(), $deadline, fn () => $this->output->write(sprintf("\r  %3d%% %s   ", $job->progress * 100, $job->message)))) {
            return $this->fail2('Timed out waiting for the render.');
        }
        $this->newLine();
        if ($job->status !== StudioJob::STATUS_DONE) {
            return $this->fail2('Render failed: '.$job->error);
        }

        $render = StudioRender::find($job->result['render_id']);
        $this->info(sprintf('Rendered %.1f s in %.0f s (total %.0f s).', $render->duration_seconds, microtime(true) - $renderStart, microtime(true) - $started));
        $this->line('File: '.$files->disk()->path($render->path));
        $this->line('Check: '.json_encode($render->check));
        $this->line('URL:  '.$files->renderUrl($render, 'video'));

        return self::SUCCESS;
    }

    private function waitFor(callable $done, int $deadline, ?callable $tick = null): bool
    {
        while (time() < $deadline) {
            if ($done()) {
                return true;
            }
            $tick && $tick();
            sleep(2);
        }

        return false;
    }

    private function fail2(string $msg): int
    {
        $this->newLine();
        $this->error($msg);

        return self::FAILURE;
    }
}
