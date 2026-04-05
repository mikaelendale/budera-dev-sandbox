<?php

use App\Models\Payment;
use App\Models\Policy;
use App\Models\WalletAccount;
use App\Services\SpendControls\ApprovalGate;
use App\Services\SpendControls\Data\PaymentRequestData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('approves when no require_approval_above', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'require_approval_above' => null,
    ]);

    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'amount_usd' => 1000,
    ]);

    $request = new PaymentRequestData(
        walletAccount: $wallet,
        amountCents: 100000,
        payment: $payment,
    );

    $decision = (new ApprovalGate)->evaluate($request);

    expect($decision->isApproved())->toBeTrue();
});

test('approves when amount below threshold', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'require_approval_above' => 500,
    ]);

    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'amount_usd' => 10,
    ]);

    $request = new PaymentRequestData(
        walletAccount: $wallet,
        amountCents: 1000,
        payment: $payment,
    );

    $decision = (new ApprovalGate)->evaluate($request);

    expect($decision->isApproved())->toBeTrue();
});

test('holds approval when amount above threshold and payment provided', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'require_approval_above' => 100,
        'approval_timeout_secs' => 3600,
    ]);

    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'amount_usd' => 500,
    ]);

    $request = new PaymentRequestData(
        walletAccount: $wallet,
        amountCents: 50000,
        payment: $payment,
    );

    $decision = (new ApprovalGate)->evaluate($request);

    expect($decision->isHeld())->toBeTrue()
        ->and($decision->holdType)->toBe('hold_approval')
        ->and($decision->approvalRequestId)->not->toBeNull()
        ->and($decision->approvalToken)->not->toBeEmpty();
});

test('approves when above threshold but no payment provided', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'require_approval_above' => 100,
    ]);

    $request = new PaymentRequestData(
        walletAccount: $wallet,
        amountCents: 50000,
        payment: null,
    );

    $decision = (new ApprovalGate)->evaluate($request);

    expect($decision->isApproved())->toBeTrue();
});
