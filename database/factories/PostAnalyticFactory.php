<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostAnalytic;
use App\Models\PostDistribution;
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

        return [
            'post_id' => Post::factory(),
            'distribution_id' => null,
            'platform' => $this->faker->randomElement(['tiktok', 'youtube', 'instagram', 'facebook']),
            'views' => $views,
            'likes' => (int) round($views * $this->faker->randomFloat(2, 0.05, 0.10)),
            'comments' => (int) round($views * $this->faker->randomFloat(2, 0.005, 0.02)),
            'shares' => (int) round($views * $this->faker->randomFloat(2, 0.01, 0.04)),
            'saves' => (int) round($views * $this->faker->randomFloat(2, 0.0, 0.02)),
            'watch_time' => $this->faker->numberBetween(30, 420),
            'watch_time_seconds' => $views * $this->faker->numberBetween(3, 20),
            'avg_view_duration_seconds' => $this->faker->numberBetween(3, 40),
            'captured_on' => now('UTC')->toDateString(),
            'recorded_at' => now(),
        ];
    }

    /** Snapshot of an existing distribution on a given day. */
    public function forDistribution(PostDistribution $distribution, ?string $capturedOn = null): static
    {
        return $this->state(fn () => [
            'post_id' => $distribution->post_id,
            'distribution_id' => $distribution->id,
            'platform' => $distribution->platform,
            'captured_on' => $capturedOn ?? now('UTC')->toDateString(),
        ]);
    }
}
