<?php

namespace Database\Factories;

use App\Models\AccountFollowerSnapshot;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountFollowerSnapshot>
 */
class AccountFollowerSnapshotFactory extends Factory
{
    protected $model = AccountFollowerSnapshot::class;

    public function definition(): array
    {
        return [
            'social_account_id' => SocialAccount::factory(),
            'platform' => 'youtube',
            'followers' => $this->faker->numberBetween(0, 50000),
            'captured_on' => now('UTC')->toDateString(),
        ];
    }
}
