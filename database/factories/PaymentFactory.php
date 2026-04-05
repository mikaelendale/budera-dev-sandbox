<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\WalletAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'wallet_account_id' => WalletAccount::factory(),
            'environment' => 'sandbox',
            'status' => 'pending',
            'direction' => 'outbound',
            'rail' => fake()->randomElement(['ach', 'wire', 'book']),
            'payee_ref' => null,
            'idempotency_key' => null,
            'amount_usd' => fake()->randomFloat(2, 1, 5000),
            'metadata' => [],
            'held_reason' => null,
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

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
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

    public function inbound(): static
    {
        return $this->state(fn () => ['direction' => 'inbound']);
    }

    public function forCategory(string $category): static
    {
        return $this->state(fn () => ['metadata' => ['category' => $category]]);
    }

    public function forPayee(string $payeeRef): static
    {
        return $this->state(fn () => ['payee_ref' => $payeeRef]);
    }

    public function amountUsd(float $amount): static
    {
        return $this->state(fn () => ['amount_usd' => $amount]);
    }
}
