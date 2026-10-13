<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $publishedAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $title = $this->faker->sentence($this->faker->numberBetween(3, 7));

        return [
            'user_id' => User::factory(),
            'social_account_id' => SocialAccount::factory(),
            'title' => rtrim($title, '.'),
            'video_url' => 'https://cdn.clippipeline.test/videos/'.str()->slug($title).'.mp4',
            'published_at' => $publishedAt,
            'tiktok_id' => 'tt_'.$this->faker->unique()->numerify('###########'),
            'caption' => $this->faker->realText(120),
            'hashtags' => [],
            'source' => Post::SOURCE_TIKTOK_INGEST,
            'download_path' => 'videos/'.now()->format('Ymd_His').'.mp4',
            'original_url' => 'https://www.tiktok.com/@demo/video/'.$this->faker->numerify('##########'),
            'downloaded_at' => $this->faker->boolean(70) ? $this->faker->dateTimeBetween($publishedAt, 'now') : null,
            'meta' => [
                'source' => 'tiktok',
                'seeded' => true,
            ],
        ];
    }

    /** A post created through POST /api/posts (upload-once). */
    public function upload(): static
    {
        return $this->state(fn () => [
            'source' => Post::SOURCE_UPLOAD,
            'social_account_id' => null,
            'tiktok_id' => null,
            'original_url' => null,
            'download_path' => null,
            'downloaded_at' => null,
            'published_at' => null,
            'video_path' => 'videos/upload.mp4',
            'meta' => null,
        ]);
    }
}
