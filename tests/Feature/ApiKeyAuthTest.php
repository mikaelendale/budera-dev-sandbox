<?php

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Support\Str;

test('api key with required ability can access wallet me endpoint', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    WalletAccount::query()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'partner_account_id' => 'mock_acct_1',
        'metadata' => [],
    ]);

    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read'],
        'metadata' => ['key_last4' => substr($plain, -4)],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->getJson('/api/v1/wallet/me')
        ->assertOk()
        ->assertJsonStructure(['wallet', 'scopes']);
});

test('api key is forbidden without required ability', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    WalletAccount::query()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'partner_account_id' => 'mock_acct_2',
        'metadata' => [],
    ]);

    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:pay'],
        'metadata' => ['key_last4' => substr($plain, -4)],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->getJson('/api/v1/wallet/me')
        ->assertForbidden();
});

test('revoked api key is rejected', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'revoked',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read'],
        'revoked_at' => now(),
        'metadata' => ['key_last4' => substr($plain, -4)],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->getJson('/api/v1/wallet/me')
        ->assertUnauthorized();
});

test('api key without wallet link ability cannot post bank links', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read'],
        'metadata' => ['key_last4' => substr($plain, -4)],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'routing_number' => '021000021',
            'account_number' => '123456789012',
        ])
        ->assertForbidden();
});

test('api key without wallet topup ability cannot post topups', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read'],
        'metadata' => ['key_last4' => substr($plain, -4)],
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_topup_auth_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/topups', [
        'wallet_account_id' => 'act_missing',
        'bank_link_id' => 'bl_missing',
        'amount_cents' => 100,
    ])
        ->assertForbidden();
});

test('sandbox api key cannot see live wallet rows', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    WalletAccount::query()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'partner_account_id' => 'mock_acct_sandbox',
        'metadata' => [],
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    WalletAccount::query()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'environment' => 'live',
        'status' => 'active',
        'partner_account_id' => 'mock_acct_live',
        'metadata' => [],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read'],
        'metadata' => ['key_last4' => substr($plain, -4)],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->getJson('/api/v1/wallet/me')
        ->assertOk()
        ->assertJsonPath('wallet.environment', 'sandbox');
});
