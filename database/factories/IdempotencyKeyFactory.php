<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\IdempotencyKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    protected $model = IdempotencyKey::class;

    public function definition(): array
    {
        return [
            'key' => fake()->uuid(),
            'company_id' => Company::factory(),
            'request_hash' => hash('sha256', fake()->sentence()),
            'response_status' => 200,
            'response_body' => ['ok' => true],
            'created_at' => now(),
        ];
    }
}
