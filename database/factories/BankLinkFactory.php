<?php

namespace Database\Factories;

use App\Models\BankLink;
use App\Models\User;
use App\Services\Audit\AuthorizationLedgerService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankLink>
 */
class BankLinkFactory extends Factory
{
    protected $model = BankLink::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wallet_account_id' => null,
            'environment' => 'sandbox',
            'status' => 'initiated',
            'bank_slug' => fake()->randomElement(['chase', 'bofa', 'wells_fargo']),
            'account_last4' => fake()->numerify('####'),
            'routing_hash' => hash('sha256', fake()->numerify('#########')),
            'encrypted_routing' => fake()->numerify('#########'),
            'encrypted_account' => fake()->numerify('############'),
            'failed_verification_attempts' => 0,
            'verified_at' => null,
            'revoked_at' => null,
            'metadata' => [],
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    public function microdepositSent(): static
    {
        return $this->state(fn () => [
            'status' => 'microdeposit_sent',
            'metadata' => [
                'microdeposit_expected_cents' => [12, 34],
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'failed_verification_attempts' => 3,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
    }

    /**
     * Pre-verified mock bank link (auto-provisioned for end users).
     */
    public function mockBank(): static
    {
        return $this->state(fn () => [
            'status' => 'verified',
            'bank_slug' => 'mock-bank',
            'account_last4' => '0000',
            'routing_hash' => hash('sha256', 'mock-bank-routing'),
            'encrypted_routing' => '000000000',
            'encrypted_account' => '000000000000',
            'verified_at' => now(),
            'metadata' => ['auto_provisioned' => true],
        ]);
    }

    /**
     * Creates a signed ACH standing authorization ledger row for this bank link (required for ACH topups).
     */
    public function withAchStandingConsent(): static
    {
        return $this->afterCreating(function (BankLink $link): void {
            $user = User::query()->find($link->user_id);
            if (! $user instanceof User) {
                return;
            }

            app(AuthorizationLedgerService::class)->recordAuthorization(
                'ach_standing',
                $user,
                null,
                (string) config('budera.ach.standing_authorization_text'),
                '127.0.0.1',
                'factory',
                $link->environment,
                ['bank_link_id' => (string) $link->getKey()],
            );
        });
    }
}
