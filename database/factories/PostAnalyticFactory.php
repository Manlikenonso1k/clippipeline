<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostAnalytic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostAnalytic>
 */
class PostAnalyticFactory extends Factory
{
    protected $model = PostAnalytic::class;

    public function definition(): array
    {
        $views = $this->faker->numberBetween(1000, 250000);
        $likes = (int) round($views * $this->faker->randomFloat(2, 0.05, 0.10));
        $comments = (int) round($views * $this->faker->randomFloat(2, 0.005, 0.02));
        $shares = (int) round($views * $this->faker->randomFloat(2, 0.01, 0.04));

        return [
            'post_id' => Post::factory(),
            'platform' => $this->faker->randomElement(['tiktok', 'youtube', 'instagram']),
            'views' => $views,
            'likes' => max(0, $likes),
            'comments' => max(0, $comments),
            'shares' => max(0, $shares),
            'watch_time' => $this->faker->numberBetween(30, 420),
            'recorded_at' => now(),
        ];
    }
}
