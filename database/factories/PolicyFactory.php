<?php

namespace Database\Factories;

use App\Models\Policy;
use App\Models\WalletAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Policy>
 */
class PolicyFactory extends Factory
{
    protected $model = Policy::class;

    public function definition(): array
    {
        return [
            'wallet_account_id' => WalletAccount::factory(),
            'agent_type' => null,
            'per_tx_limit_usd' => fake()->randomFloat(2, 50, 1000),
            'daily_spend_limit_usd' => fake()->randomFloat(2, 500, 10000),
            'daily_tx_count' => fake()->numberBetween(1, 200),
            'allowed_categories' => ['saas', 'cloud'],
            'blocked_payees' => ['blocked-example.com'],
            'require_approval_above' => fake()->randomFloat(2, 100, 5000),
            'approval_timeout_secs' => fake()->numberBetween(300, 3600),
            'max_new_payees_per_day' => fake()->numberBetween(1, 20),
            'business_hours_only' => false,
            'velocity_sensitivity' => fake()->randomElement(['low', 'medium', 'high']),
            'auto_topup' => [
                'enabled' => true,
                'threshold' => 100,
                'amount' => 500,
                'monthly_cap' => 5000,
            ],
        ];
    }
}
