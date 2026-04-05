<?php

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use App\States\WalletAccount\WalletAccountActive;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

test('wallet accounts store requires wallet:pay scope', function (): void {
    $user = User::factory()->withCompany()->create();
    Passport::actingAs($user, ['wallet:read']);

    $this->postJson('/api/v1/wallet/accounts')->assertForbidden();
});

test('wallet accounts store creates pending wallet without partner account', function (): void {
    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'secret',
    ]);

    Http::fake();

    $user = User::factory()->withCompany()->create();
    Passport::actingAs($user, ['wallet:pay']);

    $response = $this->postJson('/api/v1/wallet/accounts');

    $response->assertCreated()
        ->assertJsonMissingPath('partner_account_id')
        ->assertJsonPath('status', 'pending');

    $publicId = $response->json('id');
    expect(is_string($publicId))->toBeTrue();

    $wallet = WalletAccount::query()->where('public_id', $publicId)->firstOrFail();
    expect($wallet->partner_account_id)->toBeNull()
        ->and($wallet->status->getValue())->toBe('pending');
});

test('wallet kyc submission calls mock and stores row', function (): void {
    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'secret',
    ]);

    Http::fake([
        'http://mock-bank.test/api/kyc/submissions' => Http::response([
            'id' => 'kyc_abc',
            'status' => 'pending',
            'created_at' => '2026-01-01T00:00:00.000Z',
        ], 201),
    ]);

    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    $wallet = WalletAccount::factory()
        ->pendingWithoutPartnerAccount()
        ->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'environment' => 'sandbox',
            'metadata' => [],
        ]);

    Passport::actingAs($user, ['wallet:pay']);

    $response = $this->postJson("/api/v1/wallet/accounts/{$wallet->public_id}/kyc", [
        'legal_name' => 'Test User',
    ]);

    $response->assertCreated()
        ->assertJsonPath('wallet_account_id', $wallet->public_id);

    expect(WalletKycVerification::query()->where('mock_kyc_submission_id', 'kyc_abc')->exists())->toBeTrue();
});

test('sandbox force kyc approve creates partner account and activates wallet', function (): void {
    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'secret',
    ]);

    Http::fake([
        'http://mock-bank.test/api/accounts' => Http::response([
            'id' => 'acct_sandbox_force',
            'currency' => 'USD',
            'created_at' => '2026-01-01T00:00:00.000Z',
        ], 201),
    ]);

    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    $wallet = WalletAccount::factory()
        ->pendingWithoutPartnerAccount()
        ->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'environment' => 'sandbox',
            'metadata' => [],
        ]);

    $kyc = WalletKycVerification::query()->create([
        'wallet_account_id' => $wallet->getKey(),
        'status' => 'pending',
        'mock_kyc_submission_id' => 'kyc_force',
        'submitted_payload' => ['legal_name' => 'X'],
    ]);

    Passport::actingAs($user, ['wallet:pay']);

    $this->postJson("/api/v1/sandbox/kyc/approve/{$kyc->getKey()}")
        ->assertOk()
        ->assertJsonMissingPath('partner_account_id')
        ->assertJsonPath('wallet_status', 'active');

    $wallet->refresh();
    expect($wallet->partner_account_id)->toBe('acct_sandbox_force')
        ->and($wallet->status)->toBeInstanceOf(WalletAccountActive::class);
});
