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
            'follower_count' => $this->faker->numberBetween(5000, 500000),
            'access_token' => Str::random(64),
            'refresh_token' => Str::random(64),
            'expires_at' => now()->addDays($this->faker->numberBetween(15, 365)),
        ];
    }
}
