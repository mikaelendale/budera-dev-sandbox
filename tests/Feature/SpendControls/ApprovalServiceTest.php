<?php

use App\Models\ApprovalRequest;
use App\Models\Payment;
use App\Models\WalletAccount;
use App\Services\Ledger\LedgerService;
use App\Services\SpendControls\ApprovalService;
use App\States\Payment\PaymentFailed;
use App\States\Payment\PaymentHeldApproval;
use App\States\Payment\PaymentProcessing;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('approve resumes ACH submission and reaches processing', function (): void {
    $wallet = WalletAccount::factory()->active()->create(['balance_cents' => 0]);
    app(LedgerService::class)->credit($wallet, 1_000_000, 'manual_credit', 'approval_test_seed', 'Test opening balance');
    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'amount_usd' => 10.00,
    ]);
    $payment->status->transitionTo(PaymentHeldApproval::class);

    $approval = ApprovalRequest::factory()->create([
        'approvable_type' => Payment::class,
        'approvable_id' => $payment->id,
        'approval_token' => $token = 'tok_'.str()->random(60),
        'expires_at' => now()->addHour(),
        'status' => 'pending',
    ]);

    $ok = app(ApprovalService::class)->approve($token);

    expect($ok)->toBeTrue();
    $approval->refresh();
    expect($approval->status)->toBe('approved');
    $payment->refresh();
    expect($payment->status)->toBeInstanceOf(PaymentProcessing::class);
});

test('deny transitions payment to failed', function (): void {
    $payment = Payment::factory()->create();
    $payment->status->transitionTo(PaymentHeldApproval::class);

    $approval = ApprovalRequest::factory()->create([
        'approvable_type' => Payment::class,
        'approvable_id' => $payment->id,
        'approval_token' => $token = 'tok_'.str()->random(60),
        'expires_at' => now()->addHour(),
        'status' => 'pending',
    ]);

    $ok = app(ApprovalService::class)->deny($token);

    expect($ok)->toBeTrue();
    $approval->refresh();
    expect($approval->status)->toBe('denied');
    $payment->refresh();
    expect($payment->status)->toBeInstanceOf(PaymentFailed::class);
});

test('approve returns false when token expired', function (): void {
    $approval = ApprovalRequest::factory()->expired()->create([
        'approval_token' => $token = 'tok_'.str()->random(60),
    ]);

    $ok = app(ApprovalService::class)->approve($token);

    expect($ok)->toBeFalse();
    $approval->refresh();
    expect($approval->status)->toBe('expired');
});

test('approve returns false when token invalid', function (): void {
    $ok = app(ApprovalService::class)->approve('nonexistent-token');

    expect($ok)->toBeFalse();
});

test('approve returns false when already decided', function (): void {
    $approval = ApprovalRequest::factory()->approved()->create([
        'approval_token' => $token = 'tok_'.str()->random(60),
    ]);

    $ok = app(ApprovalService::class)->approve($token);

    expect($ok)->toBeFalse();
});
