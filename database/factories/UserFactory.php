<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_budera_admin' => false,
            'user_type' => 'developer',
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * User is the owner of a company with Spatie company_owner role.
     */
    public function withCompany(?string $companyName = null): static
    {
        return $this->afterCreating(function (User $user) use ($companyName): void {
            $company = Company::factory()->create([
                'name' => $companyName ?? 'Test Company',
                'owner_id' => $user->getKey(),
            ]);

            $teamKey = config('permission.column_names.team_foreign_key');
            $teamRole = Role::query()
                ->where('name', 'company_owner')
                ->where('guard_name', 'web')
                ->where($teamKey, $company->getKey())
                ->firstOrFail();

            setPermissionsTeamId($company->getKey());
            $user->assignRole($teamRole);
            setPermissionsTeamId(null);
        });
    }

    /**
     * End user (personal wallet, no company association).
     */
    public function endUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'end_user',
        ]);
    }

    /**
     * End user with an active personal wallet and an approved KYC verification.
     */
    public function kycVerified(): static
    {
        return $this->endUser()->afterCreating(function (User $user): void {
            $wallet = WalletAccount::query()->withoutGlobalScopes()->create([
                'company_id' => null,
                'user_id' => $user->getKey(),
                'environment' => 'sandbox',
                'status' => 'active',
                'partner_account_id' => 'mock_acct_'.bin2hex(random_bytes(8)),
                'balance_cents' => 0,
                'metadata' => ['provisioned_at_registration' => true],
            ]);

            WalletKycVerification::query()->create([
                'wallet_account_id' => $wallet->getKey(),
                'status' => 'approved',
                'session_token' => Str::random(48),
                'verified_at' => now(),
            ]);
        });
    }

    /**
     * Internal Budera admin (bypasses company onboarding).
     */
    public function buderaAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_budera_admin' => true,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
