<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletAccount>
 */
class WalletAccountFactory extends Factory
{
    protected $model = WalletAccount::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'agent_id' => null,
            'environment' => 'sandbox',
            'status' => 'pending',
            'partner_account_id' => 'mock_acct_'.fake()->unique()->numerify('########'),
            'balance_cents' => 0,
            'metadata' => [],
        ];
    }

    public function forAgent(string $agentId = 'agent_001'): static
    {
        return $this->state(fn () => ['agent_id' => $agentId]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => 'paused']);
    }

    public function frozen(): static
    {
        return $this->state(fn () => ['status' => 'frozen']);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed']);
    }

    /**
     * Personal wallet for an end user (no company association).
     */
    public function personal(): static
    {
        return $this->state(fn () => [
            'company_id' => null,
            'status' => 'active',
        ]);
    }

    /**
     * Wallet created before KYC approval (no partner bank account yet).
     */
    public function pendingWithoutPartnerAccount(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'partner_account_id' => null,
        ]);
    }
}
