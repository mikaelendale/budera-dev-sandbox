<?php

use App\Models\ApiKey;
use App\Models\ApprovalRequest;
use App\Models\Company;
use App\Models\KybReview;
use App\Models\Payment;
use App\Models\Policy;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use App\Notifications\Transactional\KybRejectedNotification;
use App\Notifications\Transactional\KycNeedsInfoNotification;
use App\Notifications\Transactional\KycRejectedNotification as WalletKycRejectedNotification;
use App\Notifications\Transactional\LowBalanceNotification;
use App\Notifications\Transactional\MicrodepositInstructionsNotification;
use App\Notifications\Transactional\PaymentHeldForApprovalNotification;
use App\Services\KybService;
use App\States\Payment\PaymentHeldApproval;
use App\States\WalletKycVerification\WalletKycVerificationNeedsInfo;
use App\States\WalletKycVerification\WalletKycVerificationPending;
use App\States\WalletKycVerification\WalletKycVerificationRejected;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\TestCase;

function notificationsTestSandboxApiKey(User $owner, array $abilities): string
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

function postMockBankWebhook(TestCase $case, array $payload, string $secret): void
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

    $case->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIGNATURE' => $signature,
    ], $body)->assertOk();
}

test('kyb rejected notifies company owner', function (): void {
    Notification::fake();

    $owner = User::factory()->withCompany('Acme')->create();
    $company = $owner->firstCompany();
    expect($company)->not->toBeNull();

    $review = KybReview::factory()->underReview()->create([
        'company_id' => $company->getKey(),
        'environment' => 'live',
    ]);

    $company->kyb_status = 'under_review';
    $company->save();

    $admin = User::factory()->buderaAdmin()->create();

    app(KybService::class)->reject($review->fresh(), $admin, 'Incomplete documentation.');

    Notification::assertSentTo($owner, KybRejectedNotification::class, function (KybRejectedNotification $n) use ($company): bool {
        return (int) $n->company->getKey() === (int) $company->getKey()
            && $n->reason === 'Incomplete documentation.';
    });
});

test('mock bank kyc rejected webhook notifies wallet user', function (): void {
    Notification::fake();
    config(['services.mock_bank.webhook_secret' => 'whsec_kyc_rej']);

    $owner = User::factory()->withCompany()->create();
    $company = $owner->firstCompany();
    expect($company)->not->toBeNull();

    $wallet = WalletAccount::factory()->pendingWithoutPartnerAccount()->create([
        'company_id' => $company->getKey(),
        'user_id' => $owner->getKey(),
        'environment' => 'sandbox',
    ]);

    WalletKycVerification::query()->create([
        'wallet_account_id' => $wallet->getKey(),
        'status' => WalletKycVerificationPending::class,
        'mock_kyc_submission_id' => 'kyc_sub_rej_web',
        'submitted_payload' => [],
    ]);

    postMockBankWebhook($this, [
        'event' => 'kyc.rejected',
        'data' => ['kyc_submission_id' => 'kyc_sub_rej_web'],
    ], 'whsec_kyc_rej');

    $kyc = WalletKycVerification::query()->where('mock_kyc_submission_id', 'kyc_sub_rej_web')->firstOrFail();
    expect($kyc->status)->toBeInstanceOf(WalletKycVerificationRejected::class);

    Notification::assertSentTo($owner, WalletKycRejectedNotification::class);
});

test('mock bank kyc needs info webhook notifies wallet user', function (): void {
    Notification::fake();
    config(['services.mock_bank.webhook_secret' => 'whsec_kyc_ni']);

    $owner = User::factory()->withCompany()->create();
    $company = $owner->firstCompany();
    expect($company)->not->toBeNull();

    $wallet = WalletAccount::factory()->pendingWithoutPartnerAccount()->create([
        'company_id' => $company->getKey(),
        'user_id' => $owner->getKey(),
        'environment' => 'sandbox',
    ]);

    WalletKycVerification::query()->create([
        'wallet_account_id' => $wallet->getKey(),
        'status' => WalletKycVerificationPending::class,
        'mock_kyc_submission_id' => 'kyc_sub_needs_info',
        'submitted_payload' => [],
    ]);

    postMockBankWebhook($this, [
        'event' => 'kyc.needs_info',
        'data' => ['kyc_submission_id' => 'kyc_sub_needs_info'],
    ], 'whsec_kyc_ni');

    $kyc = WalletKycVerification::query()->where('mock_kyc_submission_id', 'kyc_sub_needs_info')->firstOrFail();
    expect($kyc->status)->toBeInstanceOf(WalletKycVerificationNeedsInfo::class);

    Notification::assertSentTo($owner, KycNeedsInfoNotification::class);
});

test('bank link microdeposit sent notifies end user', function (): void {
    Notification::fake();

    $owner = User::factory()->withCompany('Acme')->create();
    $plain = notificationsTestSandboxApiKey($owner, ['wallet:link', 'wallet:read']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'routing_number' => '021000021',
            'account_number' => '123456789012',
            'bank_slug' => 'chase',
        ])
        ->assertCreated();

    Notification::assertSentTo($owner, MicrodepositInstructionsNotification::class);
});

test('payment held for approval notifies wallet user with web approval url', function (): void {
    Notification::fake();

    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'test-secret',
    ]);

    Http::fake([
        'http://mock-bank.test/api/transfers/ach' => Http::response([
            'transfer_id' => 'trf_held_1',
            'ref' => 'trf_held_1',
            'rail' => 'ach',
            'status' => 'pending',
            'duplicate' => false,
        ], 202),
    ]);

    $owner = User::factory()->withCompany('Acme')->create();
    $plain = notificationsTestSandboxApiKey($owner, ['wallet:pay', 'wallet:read']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => Company::query()->where('owner_id', $owner->id)->value('id'),
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_acct_held',
            'balance_cents' => 500_000,
        ]);

    Policy::query()->create([
        'wallet_account_id' => $wallet->getKey(),
        'agent_type' => null,
        'per_tx_limit_usd' => 999999,
        'daily_spend_limit_usd' => null,
        'daily_tx_count' => null,
        'allowed_categories' => [],
        'blocked_payees' => [],
        'require_approval_above' => 0,
        'approval_timeout_secs' => 3600,
        'max_new_payees_per_day' => null,
        'business_hours_only' => false,
        'velocity_sensitivity' => 'low',
        'auto_topup' => ['enabled' => false],
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_notif_held_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/payments', [
        'wallet_account_id' => $wallet->public_id,
        'amount_cents' => 1_000,
        'payee_ref' => 'vendor-held@example.com',
    ])->assertCreated();

    $payment = Payment::query()->where('wallet_account_id', $wallet->id)->firstOrFail();
    expect($payment->status)->toBeInstanceOf(PaymentHeldApproval::class);

    $approvalToken = is_array($payment->metadata) ? ($payment->metadata['approval_token'] ?? null) : null;
    expect($approvalToken)->toBeString();

    Notification::assertSentTo($owner, PaymentHeldForApprovalNotification::class, function (PaymentHeldForApprovalNotification $n) use ($approvalToken): bool {
        return str_contains($n->approvalUrl, '/payment-approvals/')
            && str_contains($n->approvalUrl, (string) $approvalToken);
    });
});

test('payment needs topup hold notifies wallet user', function (): void {
    Notification::fake();

    $owner = User::factory()->withCompany('Acme')->create();
    $plain = notificationsTestSandboxApiKey($owner, ['wallet:pay', 'wallet:read']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => Company::query()->where('owner_id', $owner->id)->value('id'),
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_acct_topup',
            'balance_cents' => 50,
        ]);

    Policy::query()->create([
        'wallet_account_id' => $wallet->getKey(),
        'agent_type' => null,
        'per_tx_limit_usd' => 999999,
        'daily_spend_limit_usd' => null,
        'daily_tx_count' => null,
        'allowed_categories' => [],
        'blocked_payees' => [],
        'require_approval_above' => null,
        'approval_timeout_secs' => 3600,
        'max_new_payees_per_day' => null,
        'business_hours_only' => false,
        'velocity_sensitivity' => 'low',
        'auto_topup' => [
            'enabled' => true,
            'threshold' => 100,
            'amount' => 500,
            'monthly_cap' => 5000,
        ],
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_notif_topup_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/payments', [
        'wallet_account_id' => $wallet->public_id,
        'amount_cents' => 10_000,
        'payee_ref' => 'vendor-topup@example.com',
    ])->assertCreated();

    Notification::assertSentTo($owner, LowBalanceNotification::class, function (LowBalanceNotification $n): bool {
        return $n->amountCents === 10_000 && $n->balanceCents === 50;
    });
});

test('authenticated owner can open payment approval inertia page for pending token', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_acct_page',
            'balance_cents' => 500_000,
        ]);

    $token = Str::random(64);

    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->getKey(),
        'environment' => 'sandbox',
        'status' => PaymentHeldApproval::class,
        'held_reason' => 'hold_approval',
        'amount_usd' => 20.00,
        'payee_ref' => 'vendor-page@example.com',
        'metadata' => ['approval_token' => $token],
    ]);

    ApprovalRequest::query()->create([
        'approvable_type' => Payment::class,
        'approvable_id' => $payment->getKey(),
        'requested_by_type' => User::class,
        'requested_by_id' => $owner->getKey(),
        'approval_token' => $token,
        'expires_at' => now()->addHour(),
        'status' => 'pending',
    ]);

    expect((int) $wallet->company_id)->toBe((int) $owner->firstCompany()->getKey());

    $this->actingAs($owner)
        ->get(route('payment-approvals.show', ['token' => $token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payment-approvals/show')
            ->where('token', $token)
            ->where('canDecide', true));
});
