<?php

use App\Models\ApiKey;
use App\Models\BankLink;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Topup;
use App\Models\Transfer;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Support\Str;

function apiHeaders(string $plain): array
{
    return [
        'Authorization' => 'Bearer '.$plain,
        'Accept' => 'application/json',
    ];
}

function seedApiKey(int $companyId, array $abilities = ['wallet:read', 'wallet:pay']): string
{
    $plain = 'sk_sandbox_test_'.Str::random(32);
    ApiKey::factory()->create([
        'company_id' => $companyId,
        'environment' => 'sandbox',
        'key_hash' => hash('sha256', $plain),
        'abilities' => $abilities,
    ]);

    return $plain;
}

test('wallet account show does not expose partner_account_id or company_id', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
    ]);
    $plain = seedApiKey($company->getKey());

    $response = $this->getJson('/api/v1/wallet/accounts/'.$wallet->public_id, apiHeaders($plain));

    $response->assertOk()
        ->assertJsonPath('id', $wallet->public_id)
        ->assertJsonMissingPath('partner_account_id')
        ->assertJsonMissingPath('company_id')
        ->assertJsonMissingPath('public_id');
});

test('wallet me does not expose user_id or partner_account_id', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
    ]);
    $plain = seedApiKey($company->getKey());

    $response = $this->getJson('/api/v1/wallet/me', apiHeaders($plain));

    $response->assertOk()
        ->assertJsonMissingPath('user_id')
        ->assertJsonMissingPath('wallet.partner_account_id');
});

test('wallet account store does not expose partner_account_id or company_id', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    $plain = seedApiKey($company->getKey(), ['wallet:pay']);

    $response = $this->postJson('/api/v1/wallet/accounts', [], apiHeaders($plain));

    $response->assertCreated()
        ->assertJsonMissingPath('partner_account_id')
        ->assertJsonMissingPath('company_id')
        ->assertJsonMissingPath('public_id');
});

test('ledger entries do not expose balance_after_cents, reference_id, or metadata', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
    ]);
    LedgerEntry::factory()->create([
        'wallet_account_id' => $wallet->getKey(),
        'type' => 'credit',
        'amount_cents' => 1000,
        'balance_after_cents' => 1000,
        'metadata' => ['internal' => 'data'],
    ]);
    $plain = seedApiKey($company->getKey());

    $response = $this->getJson('/api/v1/wallets/'.$wallet->public_id.'/ledger', apiHeaders($plain));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray()->not->toBeEmpty();

    $entry = $data[0];
    expect($entry)->not->toHaveKey('balance_after_cents')
        ->not->toHaveKey('reference_id')
        ->not->toHaveKey('metadata');
});

test('payment resource does not expose metadata', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
    ]);
    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->getKey(),
        'environment' => 'sandbox',
        'metadata' => ['secret' => 'value'],
    ]);
    $plain = seedApiKey($company->getKey());

    $response = $this->getJson('/api/v1/payments/'.$payment->public_id, apiHeaders($plain));

    $response->assertOk();
    $json = $response->json('data');
    expect($json)->not->toHaveKey('metadata');
});

test('topup resource does not expose authorization_ledger_entry_id or metadata', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
    ]);
    $bankLink = BankLink::factory()->create([
        'user_id' => $user->getKey(),
        'wallet_account_id' => $wallet->getKey(),
        'environment' => 'sandbox',
    ]);
    $topup = Topup::factory()->create([
        'wallet_account_id' => $wallet->getKey(),
        'bank_link_id' => $bankLink->getKey(),
        'environment' => 'sandbox',
        'metadata' => ['internal' => 'info'],
    ]);
    $plain = seedApiKey($company->getKey());

    $response = $this->getJson('/api/v1/topups/'.$topup->public_id, apiHeaders($plain));

    $response->assertOk();
    $json = $response->json('data');
    expect($json)->not->toHaveKey('authorization_ledger_entry_id')
        ->not->toHaveKey('metadata');
});

test('transfer resource does not expose metadata', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    $walletA = WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
    ]);
    $walletB = WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
    ]);
    $transfer = Transfer::factory()->create([
        'from_wallet_account_id' => $walletA->getKey(),
        'to_wallet_account_id' => $walletB->getKey(),
        'environment' => 'sandbox',
        'metadata' => ['internal' => 'stuff'],
    ]);
    $plain = seedApiKey($company->getKey());

    $response = $this->getJson('/api/v1/transfers/'.$transfer->public_id, apiHeaders($plain));

    $response->assertOk();
    $json = $response->json('data');
    expect($json)->not->toHaveKey('metadata');
});

test('bank link resource does not expose account_last4 or failed_verification_attempts', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
    ]);
    $bankLink = BankLink::factory()->create([
        'user_id' => $user->getKey(),
        'wallet_account_id' => $wallet->getKey(),
        'environment' => 'sandbox',
        'account_last4' => '1234',
        'failed_verification_attempts' => 2,
    ]);
    $plain = seedApiKey($company->getKey());

    $response = $this->getJson('/api/v1/bank-links/'.$bankLink->public_id, apiHeaders($plain));

    $response->assertOk();
    $json = $response->json('data');
    expect($json)->not->toHaveKey('account_last4')
        ->not->toHaveKey('failed_verification_attempts');
});

test('kyc submit does not expose internal kyc id or mock_kyc_submission_id', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
    ]);
    $plain = seedApiKey($company->getKey(), ['wallet:pay']);

    $response = $this->postJson('/api/v1/wallet/accounts/'.$wallet->public_id.'/kyc', [
        'legal_name' => 'Test User',
    ], apiHeaders($plain));

    $response->assertCreated()
        ->assertJsonMissingPath('id')
        ->assertJsonMissingPath('mock_kyc_submission_id')
        ->assertJsonPath('wallet_account_id', $wallet->public_id);
});
