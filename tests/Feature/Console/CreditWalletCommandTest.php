<?php

use App\Models\User;
use App\Models\WalletAccount;

test('budera:credit-wallet credits sandbox wallet in testing environment', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    expect($company)->not->toBeNull();

    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
        'balance_cents' => 0,
        'partner_account_id' => 'mock_acct_credit_cmd',
    ]);

    $this->artisan('budera:credit-wallet', [
        'public_id' => $wallet->public_id,
        'amount_cents' => 5_000,
    ])->assertSuccessful();

    expect($wallet->fresh()->balance_cents)->toBe(5_000);
});
