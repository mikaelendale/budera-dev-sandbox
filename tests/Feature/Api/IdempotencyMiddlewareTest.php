<?php

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Str;

function idempotencyTestApiKey(User $owner, array $abilities): string
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

test('idempotency middleware rejects invalid overlong key', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = idempotencyTestApiKey($owner, ['wallet:transfer', 'wallet:read']);

    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $from = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_from_long',
            'balance_cents' => 1_000,
        ]);

    $to = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_to_long',
            'balance_cents' => 0,
        ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => str_repeat('k', 256),
    ])
        ->postJson('/api/v1/transfers', [
            'from_wallet_account_id' => $from->public_id,
            'to_wallet_account_id' => $to->public_id,
            'amount_cents' => 100,
        ])
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_INVALID');
});

test('idempotency middleware rejects missing header', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = idempotencyTestApiKey($owner, ['wallet:transfer', 'wallet:read']);

    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $from = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_from_idem',
            'balance_cents' => 1_000,
        ]);

    $to = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_to_idem',
            'balance_cents' => 0,
        ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/transfers', [
            'from_wallet_account_id' => $from->public_id,
            'to_wallet_account_id' => $to->public_id,
            'amount_cents' => 100,
        ])
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
});

test('idempotency middleware replays identical request with same key', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = idempotencyTestApiKey($owner, ['wallet:transfer', 'wallet:read']);

    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $from = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_from_replay',
            'balance_cents' => 10_000,
        ]);

    app(LedgerService::class)->credit($from, 10_000, 'seed', (string) Str::uuid(), 'Test seed');

    $to = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_to_replay',
            'balance_cents' => 0,
        ]);

    $payload = [
        'from_wallet_account_id' => $from->public_id,
        'to_wallet_account_id' => $to->public_id,
        'amount_cents' => 500,
    ];

    $key = 'idem_replay_'.Str::uuid()->toString();

    $first = $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/transfers', $payload);

    $first->assertCreated();

    $second = $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/transfers', $payload);

    $second->assertCreated();
    expect($second->json())->toBe($first->json());
});

test('idempotency middleware returns conflict when key reused with different body', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = idempotencyTestApiKey($owner, ['wallet:transfer', 'wallet:read']);

    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $from = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_from_cf',
            'balance_cents' => 20_000,
        ]);

    app(LedgerService::class)->credit($from, 20_000, 'seed', (string) Str::uuid(), 'Test seed');

    $to = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_to_cf',
            'balance_cents' => 0,
        ]);

    $key = 'idem_cf_'.Str::uuid()->toString();

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/transfers', [
        'from_wallet_account_id' => $from->public_id,
        'to_wallet_account_id' => $to->public_id,
        'amount_cents' => 100,
    ])->assertCreated();

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/transfers', [
        'from_wallet_account_id' => $from->public_id,
        'to_wallet_account_id' => $to->public_id,
        'amount_cents' => 200,
    ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_CONFLICT');
});
