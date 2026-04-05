<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Models\WalletOauthGrant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletOauthGrant>
 */
class WalletOauthGrantFactory extends Factory
{
    protected $model = WalletOauthGrant::class;

    public function definition(): array
    {
        return [
            'oauth_access_token_id' => Str::random(40),
            'user_id' => User::factory(),
            'oauth_client_id' => null,
            'company_id' => Company::factory(),
            'wallet_account_id' => null,
            'scopes' => ['wallet:read', 'wallet:pay'],
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_at' => now(),
        ]);
    }
}
