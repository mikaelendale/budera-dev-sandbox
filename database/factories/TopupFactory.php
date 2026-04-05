<?php

namespace Database\Factories;

use App\Models\Topup;
use App\Models\WalletAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Topup>
 */
class TopupFactory extends Factory
{
    protected $model = Topup::class;

    public function definition(): array
    {
        return [
            'wallet_account_id' => WalletAccount::factory(),
            'bank_link_id' => null,
            'environment' => 'sandbox',
            'status' => 'pending',
            'amount_usd' => fake()->randomFloat(2, 1, 5000),
            'idempotency_key' => null,
            'metadata' => [],
            'settled_at' => null,
        ];
    }

    public function settled(): static
    {
        return $this->state(fn () => [
            'status' => 'settled',
            'settled_at' => now(),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => 'processing']);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed']);
    }

    public function returned(): static
    {
        return $this->state(fn () => ['status' => 'returned']);
    }
}
