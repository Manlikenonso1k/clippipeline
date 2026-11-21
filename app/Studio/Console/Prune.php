<?php

namespace App\Studio\Console;

use App\Studio\Models\StudioAsset;
use App\Studio\Models\StudioJob;
use App\Studio\Services\StudioCleanup;
use App\Studio\Services\StudioFiles;
use Illuminate\Console\Command;

/** Retention (config clippipeline.studio.retention): frees disk space without touching anything still in use. */
class Prune extends Command
{
    protected $signature = 'studio:prune {--dry-run : Show what would be deleted and how much space it frees}';

    protected $description = 'Delete replaced and old Studio renders, idle preview proxies, long-unused uploads and old finished jobs.';

    public function handle(StudioCleanup $cleanup, StudioFiles $files): int
    {
        $dry = (bool) $this->option('dry-run');

        $superseded = $cleanup->supersededRenders(null, $dry);
        $previews = $cleanup->oldPreviews($dry);
        $proxies = $cleanup->idleProxies($dry);
        $sources = $cleanup->idleSources($dry);

        // Reference media is deleted after analysis; this catches any that failed half-way.
        $refs = StudioAsset::query()->where('role', 'reference')->whereNotNull('path')->where('updated_at', '<', now()->subDay())->get();
        foreach ($refs as $a) {
            if (! $dry) {
                $files->delete([$a->path, $a->preview_proxy_path]);
                $a->forceFill(['path' => null, 'preview_proxy_path' => null])->save();
            }
        }

        $jobs = StudioJob::query()->whereIn('status', [StudioJob::STATUS_DONE, StudioJob::STATUS_FAILED, StudioJob::STATUS_CANCELED])
            ->where('updated_at', '<', now()->subDays(30));
        $jobCount = $dry ? $jobs->count() : $jobs->delete();

        $mb = fn (int $b) => number_format($b / 1048576, 1).' MB';
        $total = $superseded['bytes'] + $previews['bytes'] + $proxies['bytes'] + $sources['bytes'];
        $this->info(sprintf(
            '%s%d replaced render(s), %d old preview(s), %d proxy set(s), %d unused upload(s), %d reference file(s), %d old job(s). Space %s: %s.',
            $dry ? '[dry run] would delete ' : 'Deleted ',
            $superseded['count'], $previews['count'], $proxies['count'], $sources['count'], $refs->count(), $jobCount,
            $dry ? 'to free' : 'freed', $mb($total),
        ));

        return self::SUCCESS;
    }
}
