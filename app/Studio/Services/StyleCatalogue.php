<?php

namespace App\Studio\Services;

/** Style packs and the music library from motion/dist/styles.json (built by `npm run templates:build`). */
class StyleCatalogue
{
    private ?array $data = null;

    private function data(): array
    {
        if ($this->data === null) {
            $file = base_path('motion/dist/styles.json');
            $this->data = is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : ['styles' => [], 'music' => []];
        }

        return $this->data;
    }

    /** @return array<int, array> */
    public function styles(): array
    {
        return array_values((array) ($this->data()['styles'] ?? []));
    }

    public function style(string $slug): ?array
    {
        foreach ($this->styles() as $s) {
            if (($s['slug'] ?? null) === $slug) {
                return $s;
            }
        }

        return null;
    }

    /** @return array<int, array> */
    public function music(): array
    {
        return array_values((array) ($this->data()['music'] ?? []));
    }

    public function hasMusic(string $slug): bool
    {
        return collect($this->music())->contains('slug', $slug) && is_file($this->musicPath($slug));
    }

    public function musicPath(string $slug): string
    {
        return base_path('motion/public/music/'.basename($slug).'.mp3');
    }

    public function musicUrl(string $slug): string
    {
        return route('studio.music', ['slug' => $slug]);
    }
}
