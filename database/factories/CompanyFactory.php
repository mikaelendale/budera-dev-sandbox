<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'email' => fake()->unique()->companyEmail(),
            'owner_id' => User::factory(),
            'kyb_status' => 'not_started',
            'api_rate_limit_tier' => 'default',
        ];
    }

    public function kybApproved(): static
    {
        return $this->state(fn () => [
            'kyb_status' => 'approved',
            'live_enabled_at' => now(),
        ]);
    }

    public function apiRateTier(string $tier): static
    {
        return $this->state(fn () => [
            'api_rate_limit_tier' => $tier,
        ]);
    }
}
