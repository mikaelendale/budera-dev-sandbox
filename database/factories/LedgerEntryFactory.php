<?php

namespace Database\Factories;

use App\Models\LedgerEntry;
use App\Models\WalletAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    public function definition(): array
    {
        return [
            'wallet_account_id' => WalletAccount::factory(),
            'type' => fake()->randomElement(['credit', 'debit']),
            'amount_cents' => fake()->numberBetween(100, 500000),
            'reference_type' => fake()->randomElement(['payment', 'topup', 'transfer', 'fee', 'reversal']),
            'reference_id' => (string) str()->uuid(),
            'balance_after_cents' => fake()->numberBetween(0, 1000000),
            'description' => fake()->sentence(),
            'metadata' => [],
            'created_at' => now(),
        ];
    }

    public function credit(int $amountCents = 10000, int $balanceAfter = 10000): static
    {
        return $this->state(fn () => [
            'type' => 'credit',
            'amount_cents' => $amountCents,
            'reference_type' => 'topup',
            'balance_after_cents' => $balanceAfter,
        ]);
    }

    public function debit(int $amountCents = 5000, int $balanceAfter = 5000): static
    {
        return $this->state(fn () => [
            'type' => 'debit',
            'amount_cents' => $amountCents,
            'reference_type' => 'payment',
            'balance_after_cents' => $balanceAfter,
        ]);
    }
}
