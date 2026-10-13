<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    public function definition(): array
    {
        $platform = $this->faker->randomElement(['tiktok', 'youtube', 'instagram']);

        return [
            'user_id' => User::factory(),
            'provider' => $platform,
            'platform_name' => $platform,
            'handle' => $platform.'_'.$this->faker->unique()->userName(),
            'display_name' => $this->faker->name(),
            'external_id' => (string) $this->faker->unique()->numberBetween(100000, 999999999),
            'follower_count' => $this->faker->numberBetween(5000, 500000),
            'access_token' => Str::random(64),
            'refresh_token' => Str::random(64),
            'expires_at' => now()->addDays($this->faker->numberBetween(15, 365)),
            'status' => SocialAccount::STATUS_ACTIVE,
        ];
    }

    public function platform(string $platform): static
    {
        return $this->state(fn () => ['provider' => $platform, 'platform_name' => $platform]);
    }

    public function needsReauth(string $reason = 'Token revoked.'): static
    {
        return $this->state(fn () => ['status' => SocialAccount::STATUS_NEEDS_REAUTH, 'last_error' => $reason]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }
}
