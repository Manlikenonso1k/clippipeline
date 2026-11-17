<?php

namespace App\Studio\Console;

use App\Studio\Models\StudioTemplate;
use App\Studio\Services\JobQueue;
use App\Studio\Services\ServiceUser;
use Illuminate\Console\Command;

/** Upserts studio_templates from motion/dist/templates.json and queues gallery previews for new versions. */
class TemplatesSync extends Command
{
    protected $signature = 'studio:templates-sync {--file= : Path to templates.json} {--no-previews} {--force-previews}';

    protected $description = 'Register Studio motion-graphics templates from motion/dist/templates.json.';

    public function handle(JobQueue $jobs): int
    {
        $file = $this->option('file') ?: base_path('motion/dist/templates.json');
        if (! is_file($file)) {
            $this->warn("No catalogue at {$file}. Run `npm run templates:build` in motion/ first.");

            return self::SUCCESS;
        }
        $catalogue = json_decode((string) file_get_contents($file), true);
        $list = (array) ($catalogue['templates'] ?? $catalogue ?? []);

        $new = 0;
        $queued = 0;
        $service = null;
        foreach ($list as $i => $t) {
            if (! isset($t['slug'], $t['version'], $t['name'], $t['kind'])) {
                $this->warn('Skipping an entry without slug/version/name/kind.');

                continue;
            }
            $model = StudioTemplate::query()->firstOrNew(['slug' => $t['slug'], 'version' => (int) $t['version']]);
            $isNew = ! $model->exists;
            $model->fill([
                'name' => $t['name'],
                'categories' => array_values((array) ($t['categories'] ?? [])),
                'kind' => $t['kind'],
                'aspects' => array_values((array) ($t['aspects'] ?? ['9:16', '1:1', '16:9'])),
                'default_duration' => (float) ($t['defaultDuration'] ?? 3),
                'min_duration' => (float) ($t['minDuration'] ?? 0.5),
                'max_duration' => (float) ($t['maxDuration'] ?? 30),
                'props_schema' => (array) ($t['propsSchema'] ?? ['type' => 'object', 'properties' => []]),
                'default_props' => (array) ($t['defaultProps'] ?? []),
                'credit' => $t['credit'] ?? null,
                'sort' => (int) ($t['sort'] ?? $i),
            ]);
            if ($isNew) {
                // A credited adaptation stays hidden until permission is confirmed.
                $model->is_active = empty($t['credit']) ? (bool) ($t['isActive'] ?? true) : false;
                $new++;
            }
            $model->save();

            if (! $this->option('no-previews') && ($isNew || $this->option('force-previews'))) {
                $service ??= ServiceUser::get();
                foreach ($model->aspects as $aspect) {
                    $jobs->dispatch('render_template_preview', $service->id, null, null, [
                        'slug' => $model->slug, 'version' => $model->version, 'aspect' => $aspect, 'props' => $model->default_props,
                    ], -5);
                    $queued++;
                }
            }
        }

        $this->info("Templates: {$new} new, ".count($list)." in catalogue, {$queued} preview render(s) queued.");

        return self::SUCCESS;
    }
}
