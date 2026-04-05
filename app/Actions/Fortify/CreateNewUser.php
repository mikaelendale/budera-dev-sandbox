<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\BankLink;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRegistrationRules(),
            'user_type' => ['required', 'string', 'in:developer,end_user'],
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'user_type' => $input['user_type'],
            ]);

            if ($user->isEndUser()) {
                $this->provisionPersonalWallet($user);
            }

            return $user;
        });
    }

    /**
     * End users get a personal wallet with a mock-bank link on signup.
     */
    private function provisionPersonalWallet(User $user): void
    {
        $wallet = WalletAccount::query()->create([
            'company_id' => null,
            'user_id' => $user->getKey(),
            'environment' => 'sandbox',
            'status' => 'pending',
            'partner_account_id' => 'mock_acct_'.bin2hex(random_bytes(8)),
            'balance_cents' => 0,
            'metadata' => ['provisioned_at_registration' => true],
        ]);

        BankLink::query()->create([
            'user_id' => $user->getKey(),
            'wallet_account_id' => $wallet->getKey(),
            'environment' => 'sandbox',
            'status' => 'verified',
            'bank_slug' => 'mock-bank',
            'account_last4' => '0000',
            'routing_hash' => hash('sha256', 'mock-bank-routing'),
            'encrypted_routing' => '000000000',
            'encrypted_account' => '000000000000',
            'verified_at' => now(),
            'metadata' => ['auto_provisioned' => true],
        ]);

        WalletKycVerification::query()->create([
            'wallet_account_id' => $wallet->getKey(),
            'status' => 'not_started',
            'session_token' => Str::random(48),
            'session_expires_at' => now()->addDays(7),
        ]);
    }
}
