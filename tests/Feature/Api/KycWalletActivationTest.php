<?php

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use App\Models\WebhookOutbox;
use App\Notifications\Transactional\KycApprovedNotification;
use App\States\WalletAccount\WalletAccountActive;
use App\States\WalletKycVerification\WalletKycVerificationApproved;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

test('wallet activates end to end after kyc submission and kyc verified webhook', function (): void {
    Notification::fake();
    config(['services.mock_bank.webhook_secret' => 'whsec_test']);
    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'secret',
    ]);

    Http::fake([
        'http://mock-bank.test/api/kyc/submissions' => Http::response([
            'id' => 'kyc_e2e',
            'status' => 'pending',
            'created_at' => '2026-01-01T00:00:00.000Z',
        ], 201),
        'http://mock-bank.test/api/accounts' => Http::response([
            'id' => 'acct_e2e',
            'currency' => 'USD',
            'created_at' => '2026-01-01T00:00:00.000Z',
        ], 201),
    ]);

    $user = User::factory()->withCompany()->create();
    Passport::actingAs($user, ['wallet:pay']);

    $create = $this->postJson('/api/v1/wallet/accounts');
    $create->assertCreated();
    $publicId = $create->json('id');
    expect(is_string($publicId))->toBeTrue();

    $this->postJson("/api/v1/wallet/accounts/{$publicId}/kyc", [
        'legal_name' => 'End To End',
    ])->assertCreated()
        ->assertJsonPath('wallet_account_id', $publicId);

    $kyc = WalletKycVerification::query()->where('mock_kyc_submission_id', 'kyc_e2e')->firstOrFail();

    $payload = [
        'event' => 'kyc.verified',
        'id' => 'evt_kyc_e2e',
        'occurred_at' => now()->toIso8601String(),
        'data' => [
            'kyc_submission_id' => 'kyc_e2e',
            'account_id' => 'ignored',
        ],
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'whsec_test');

    $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIGNATURE' => $signature,
    ], $body)->assertOk();

    expect($kyc->fresh()->status)->toBeInstanceOf(WalletKycVerificationApproved::class);

    $wallet = WalletAccount::query()->where('public_id', $publicId)->firstOrFail();
    expect($wallet->partner_account_id)->toBe('acct_e2e')
        ->and($wallet->status)->toBeInstanceOf(WalletAccountActive::class);

    expect(WebhookOutbox::query()->where('event', 'account.active')->exists())->toBeTrue();

    Notification::assertSentTo($user, KycApprovedNotification::class);
});
