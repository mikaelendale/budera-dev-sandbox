<?php

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'test-secret',
    ]);
});

function makeSandboxApiKey(User $owner, array $abilities): string
{
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => $abilities,
        'metadata' => [],
    ]);

    return $plain;
}

test('post payments creates outbound payment and calls mock bank', function (): void {
    Http::fake([
        'http://mock-bank.test/api/transfers/ach' => Http::response([
            'transfer_id' => 'trf_test_1',
            'ref' => 'trf_test_1',
            'rail' => 'ach',
            'status' => 'pending',
            'duplicate' => false,
        ], 202),
    ]);

    $owner = User::factory()->withCompany('Acme')->create();
    $plain = makeSandboxApiKey($owner, ['wallet:pay', 'wallet:read']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => Company::query()->where('owner_id', $owner->id)->value('id'),
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_acct_pay',
            'balance_cents' => 0,
        ]);
    app(LedgerService::class)->credit($wallet, 500_000, 'manual_credit', 'pay_test_open', 'Payments API test balance');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_pay_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/payments', [
        'wallet_account_id' => $wallet->public_id,
        'amount_cents' => 1_000,
        'payee_ref' => 'vendor@example.com',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'processing');

    $payment = Payment::query()->where('wallet_account_id', $wallet->id)->firstOrFail();
    expect($payment->metadata['bank_transfer_id'] ?? null)->toBe('trf_test_1');
});

test('post payments transitions to failed when mock bank ach push errors after approval', function (): void {
    Http::fake([
        'http://mock-bank.test/api/transfers/ach' => Http::response(['error' => 'upstream_unavailable'], 500),
    ]);

    $owner = User::factory()->withCompany('Acme')->create();
    $plain = makeSandboxApiKey($owner, ['wallet:pay', 'wallet:read']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => Company::query()->where('owner_id', $owner->id)->value('id'),
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_acct_bank_err',
            'balance_cents' => 0,
        ]);
    app(LedgerService::class)->credit($wallet, 500_000, 'manual_credit', 'pay_bank_err_open', 'Payments API test balance');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_pay_bank_err_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/payments', [
        'wallet_account_id' => $wallet->public_id,
        'amount_cents' => 1_000,
        'payee_ref' => 'vendor@example.com',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.held_reason', 'bank_error');

    $payment = Payment::query()->where('wallet_account_id', $wallet->id)->firstOrFail();
    expect($payment->status->getValue())->toBe('failed');
});

test('payment idempotency returns same body for duplicate key and matching body', function (): void {
    Http::fake([
        'http://mock-bank.test/api/transfers/ach' => Http::response([
            'transfer_id' => 'trf_idem',
            'ref' => 'trf_idem',
            'rail' => 'ach',
            'status' => 'pending',
            'duplicate' => false,
        ], 202),
    ]);

    $owner = User::factory()->withCompany('Acme')->create();
    $plain = makeSandboxApiKey($owner, ['wallet:pay', 'wallet:read']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => Company::query()->where('owner_id', $owner->id)->value('id'),
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_acct_idem',
            'balance_cents' => 0,
        ]);
    app(LedgerService::class)->credit($wallet, 500_000, 'manual_credit', 'pay_idem_open', 'Payments API test balance');

    $payload = [
        'wallet_account_id' => $wallet->public_id,
        'amount_cents' => 2_000,
        'payee_ref' => 'a@b.com',
    ];

    $key = 'idem_'.Str::uuid()->toString();

    $first = $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/payments', $payload);

    $first->assertCreated();

    $second = $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/payments', $payload);

    $second->assertStatus(201);
    expect($second->json())->toBe($first->json());
});

test('get payments requires wallet read ability', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = makeSandboxApiKey($owner, ['wallet:pay']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->getJson('/api/v1/payments')
        ->assertForbidden();
});
