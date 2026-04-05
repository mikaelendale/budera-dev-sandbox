<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CompanyInvitation>
 */
class CompanyInvitationFactory extends Factory
{
    protected $model = CompanyInvitation::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'email' => fake()->unique()->safeEmail(),
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'accepted_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
