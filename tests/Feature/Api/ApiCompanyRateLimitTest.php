<?php

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

test('api v1 returns 429 with retry-after when per-company limit exceeded', function (): void {
    Cache::flush();
    config(['budera.api_rate_limits.default' => 2]);

    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    WalletAccount::query()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'partner_account_id' => 'mock_acct_rl',
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

    $headers = ['Authorization' => 'Bearer '.$plain];

    $this->withHeaders($headers)->getJson('/api/v1/wallet/me')->assertOk();
    $this->withHeaders($headers)->getJson('/api/v1/wallet/me')->assertOk();

    $this->withHeaders($headers)->getJson('/api/v1/wallet/me')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('error.code', 'rate_limit_exceeded');
});
