<?php

namespace Database\Factories;

use App\Models\Transfer;
use App\Models\WalletAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    public function definition(): array
    {
        return [
            'from_wallet_account_id' => WalletAccount::factory(),
            'to_wallet_account_id' => WalletAccount::factory(),
            'environment' => 'sandbox',
            'status' => 'pending',
            'amount_usd' => fake()->randomFloat(2, 1, 5000),
            'idempotency_key' => null,
            'metadata' => [],
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed']);
    }
}
