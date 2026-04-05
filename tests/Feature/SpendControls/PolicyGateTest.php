<?php

use App\Models\Payment;
use App\Models\Policy;
use App\Models\WalletAccount;
use App\Services\SpendControls\Data\PaymentRequestData;
use App\Services\SpendControls\PolicyGate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('approves when no policy', function (): void {
    $wallet = WalletAccount::factory()->create();
    $request = new PaymentRequestData(
        walletAccount: $wallet,
        amountCents: 10000,
    );

    $decision = (new PolicyGate)->evaluate($request);

    expect($decision->isApproved())->toBeTrue();
});

test('rejects when per_tx_limit exceeded', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'per_tx_limit_usd' => 50,
        'daily_spend_limit_usd' => null,
        'daily_tx_count' => null,
    ]);

    $request = new PaymentRequestData($wallet, 6000); // $60

    $decision = (new PolicyGate)->evaluate($request);

    expect($decision->isRejected())->toBeTrue()
        ->and($decision->reasonCode)->toBe('per_tx_limit_exceeded');
});

test('rejects when daily_tx_count exceeded', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'per_tx_limit_usd' => null,
        'daily_spend_limit_usd' => null,
        'daily_tx_count' => 2,
    ]);

    Payment::factory()->count(2)->create(['wallet_account_id' => $wallet->id]);

    $request = new PaymentRequestData($wallet, 1000);

    $decision = (new PolicyGate)->evaluate($request);

    expect($decision->isRejected())->toBeTrue()
        ->and($decision->reasonCode)->toBe('daily_tx_count_exceeded');
});

test('rejects when daily_spend_limit exceeded', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'per_tx_limit_usd' => null,
        'daily_spend_limit_usd' => 100,
        'daily_tx_count' => null,
    ]);

    Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'amount_usd' => 60,
    ]);

    $request = new PaymentRequestData($wallet, 5000); // $50, total would be $110

    $decision = (new PolicyGate)->evaluate($request);

    expect($decision->isRejected())->toBeTrue()
        ->and($decision->reasonCode)->toBe('daily_spend_limit_exceeded');
});

test('rejects when category not in allowed list', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'allowed_categories' => ['saas', 'cloud'],
    ]);

    $request = new PaymentRequestData(
        walletAccount: $wallet,
        amountCents: 1000,
        category: 'gambling',
    );

    $decision = (new PolicyGate)->evaluate($request);

    expect($decision->isRejected())->toBeTrue()
        ->and($decision->reasonCode)->toBe('category_not_allowed');
});

test('rejects when payee is blocked', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'blocked_payees' => ['blocked.com', 'evil'],
    ]);

    $request = new PaymentRequestData(
        walletAccount: $wallet,
        amountCents: 1000,
        payeeRef: 'payee-blocked.com-123',
    );

    $decision = (new PolicyGate)->evaluate($request);

    expect($decision->isRejected())->toBeTrue()
        ->and($decision->reasonCode)->toBe('payee_blocked');
});

test('rejects when outside business hours', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'business_hours_only' => true,
    ]);

    Carbon::setTestNow(Carbon::create(2026, 3, 23, 22, 0, 0, 'UTC')); // Sunday 10pm

    $request = new PaymentRequestData($wallet, 1000);

    $decision = (new PolicyGate)->evaluate($request);

    expect($decision->isRejected())->toBeTrue()
        ->and($decision->reasonCode)->toBe('outside_business_hours');

    Carbon::setTestNow();
});

test('rejects when max_new_payees_per_day exceeded', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'max_new_payees_per_day' => 1,
        'daily_spend_limit_usd' => 100_000,
        'daily_tx_count' => 10_000,
        'per_tx_limit_usd' => 100_000,
    ]);

    Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'payee_ref' => 'payee-a',
    ]);

    $request = new PaymentRequestData(
        walletAccount: $wallet,
        amountCents: 1000,
        payeeRef: 'payee-b',
    );

    $decision = (new PolicyGate)->evaluate($request);

    expect($decision->isRejected())->toBeTrue()
        ->and($decision->reasonCode)->toBe('max_new_payees_exceeded');
});

test('approves when all rules pass', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'per_tx_limit_usd' => 100,
        'daily_spend_limit_usd' => 500,
        'daily_tx_count' => 10,
        'allowed_categories' => ['saas'],
        'blocked_payees' => ['blocked.com'],
        'business_hours_only' => false,
        'max_new_payees_per_day' => 5,
    ]);

    $request = new PaymentRequestData(
        walletAccount: $wallet,
        amountCents: 5000,
        category: 'saas',
        payeeRef: 'vendor-ok.com',
    );

    $decision = (new PolicyGate)->evaluate($request);

    expect($decision->isApproved())->toBeTrue();
});
