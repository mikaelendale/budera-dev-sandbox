<?php

use App\Models\ApiKey;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Support\Str;

test('api wallet show succeeds when public_id matches company and key environment', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    expect($company)->not->toBeNull();

    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
    ]);

    $plain = 'sk_sandbox_ok_'.Str::random(32);
    ApiKey::factory()->create([
        'company_id' => $company->getKey(),
        'environment' => 'sandbox',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read'],
    ]);

    $this->getJson('/api/v1/wallet/accounts/'.$wallet->public_id, [
        'Authorization' => 'Bearer '.$plain,
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('id', $wallet->public_id);
});

test('api wallet show returns wallet_not_in_company for wallet owned by another company', function (): void {
    $userA = User::factory()->withCompany()->create();
    $companyA = $userA->firstCompany();
    expect($companyA)->not->toBeNull();

    $userB = User::factory()->withCompany()->create();
    $companyB = $userB->firstCompany();
    expect($companyB)->not->toBeNull();

    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $companyA->getKey(),
        'user_id' => $userA->getKey(),
        'environment' => 'sandbox',
    ]);

    $plain = 'sk_sandbox_b_'.Str::random(32);
    ApiKey::factory()->create([
        'company_id' => $companyB->getKey(),
        'environment' => 'sandbox',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read'],
    ]);

    $this->getJson('/api/v1/wallet/accounts/'.$wallet->public_id, [
        'Authorization' => 'Bearer '.$plain,
        'Accept' => 'application/json',
    ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'wallet_not_in_company');
});

test('api wallet show returns wallet_environment_mismatch when key environment differs from wallet', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    expect($company)->not->toBeNull();

    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
    ]);

    $plain = 'sk_live_mismatch_'.Str::random(32);
    ApiKey::factory()->live()->create([
        'company_id' => $company->getKey(),
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read'],
    ]);

    $this->getJson('/api/v1/wallet/accounts/'.$wallet->public_id, [
        'Authorization' => 'Bearer '.$plain,
        'Accept' => 'application/json',
    ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'wallet_environment_mismatch')
        ->assertJsonPath('error.detail.wallet_environment', 'sandbox')
        ->assertJsonPath('error.detail.key_environment', 'live');
});

test('api wallet show returns resource_not_found for unknown public_id', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    expect($company)->not->toBeNull();

    $plain = 'sk_sandbox_nf_'.Str::random(32);
    ApiKey::factory()->create([
        'company_id' => $company->getKey(),
        'environment' => 'sandbox',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read'],
    ]);

    $this->getJson('/api/v1/wallet/accounts/act_nonexistentzzzzzzzzzzzzzzzz', [
        'Authorization' => 'Bearer '.$plain,
        'Accept' => 'application/json',
    ])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'resource_not_found');
});
