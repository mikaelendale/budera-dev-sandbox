<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'environment' => 'sandbox',
            'status' => 'active',
            'key_hash' => hash('sha256', Str::random(40)),
            'abilities' => ['wallet:read', 'wallet:pay'],
            'revoked_at' => null,
            'metadata' => [],
        ];
    }

    public function live(): static
    {
        return $this->state(fn () => ['environment' => 'live']);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
    }
}
