<?php

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use App\Models\WebhookOutbox;
use App\States\WalletAccount\WalletAccountActive;
use App\States\WalletKycVerification\WalletKycVerificationApproved;
use Illuminate\Support\Facades\Http;

test('mock bank webhook returns 503 when secret not configured', function (): void {
    config(['services.mock_bank.webhook_secret' => null]);

    $response = $this->postJson('/api/webhooks/mock-bank', ['event' => 'test']);

    $response->assertStatus(503)
        ->assertJsonPath('error.code', 'webhook_not_configured');
});

test('mock bank webhook rejects invalid signature', function (): void {
    config(['services.mock_bank.webhook_secret' => 'whsec_test']);

    $body = json_encode(['event' => 'ach.settled', 'id' => 'evt_1'], JSON_THROW_ON_ERROR);

    $response = $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIGNATURE' => 'sha256=deadbeef',
    ], $body);

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_signature');
});

test('mock bank webhook accepts valid hmac', function (): void {
    config(['services.mock_bank.webhook_secret' => 'whsec_test']);

    $body = json_encode(['event' => 'ach.settled', 'id' => 'evt_1'], JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'whsec_test');

    $response = $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIGNATURE' => $signature,
    ], $body);

    $response->assertOk()
        ->assertJson(['received' => true]);
});

test('mock bank webhook kyc verified updates verification row and activates wallet', function (): void {
    config(['services.mock_bank.webhook_secret' => 'whsec_test']);

    Http::fake([
        'http://mock-bank.test/api/accounts' => Http::response([
            'id' => 'acct_webhook_kyc',
            'currency' => 'USD',
            'created_at' => '2026-01-01T00:00:00.000Z',
        ], 201),
    ]);

    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'secret',
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
        'mock_kyc_submission_id' => 'kyc_123',
        'submitted_payload' => ['legal_name' => 'A'],
    ]);

    $payload = [
        'event' => 'kyc.verified',
        'id' => 'evt_kyc',
        'occurred_at' => now()->toIso8601String(),
        'data' => [
            'kyc_submission_id' => 'kyc_123',
            'account_id' => 'ignored_until_activation',
        ],
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'whsec_test');

    $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIGNATURE' => $signature,
    ], $body)->assertOk();

    expect($kyc->fresh()->status)->toBeInstanceOf(WalletKycVerificationApproved::class)
        ->and($kyc->fresh()->verified_at)->not->toBeNull();

    $wallet->refresh();
    expect($wallet->partner_account_id)->toBe('acct_webhook_kyc')
        ->and($wallet->status)->toBeInstanceOf(WalletAccountActive::class);

    expect(WebhookOutbox::query()->where('event', 'account.active')->exists())->toBeTrue();
});
