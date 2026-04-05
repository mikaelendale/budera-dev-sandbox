<?php

use App\Jobs\RunComplianceScreenJob;
use App\Models\Payment;
use App\Models\Policy;
use App\Models\WalletAccount;
use App\Services\SpendControls\Data\PaymentRequestData;
use App\Services\SpendControls\SpendControlsPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
});

test('short-circuits on policy rejection', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'per_tx_limit_usd' => 10,
    ]);

    $request = new PaymentRequestData($wallet, 2000);

    $pipeline = app(SpendControlsPipeline::class);
    $decision = $pipeline->evaluate($request);

    expect($decision->isRejected())->toBeTrue()
        ->and($decision->layer)->toBe('policy');
    Queue::assertNotPushed(RunComplianceScreenJob::class);
});

test('short-circuits on balance hold', function (): void {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 0]);
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'auto_topup' => ['enabled' => true],
    ]);

    $request = new PaymentRequestData($wallet, 5000);

    $pipeline = app(SpendControlsPipeline::class);
    $decision = $pipeline->evaluate($request);

    expect($decision->isHeld())->toBeTrue()
        ->and($decision->holdType)->toBe('needs_topup');
    Queue::assertNotPushed(RunComplianceScreenJob::class);
});

test('short-circuits on velocity hold', function (): void {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 100000]);
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'per_tx_limit_usd' => 1000,
        'daily_spend_limit_usd' => 100000,
        'daily_tx_count' => 50,
        'velocity_sensitivity' => 'medium',
        'max_new_payees_per_day' => null,
        'auto_topup' => ['enabled' => false],
    ]);
    foreach ([10, 11, 9, 10, 12] as $amount) {
        Payment::factory()->create([
            'wallet_account_id' => $wallet->id,
            'amount_usd' => $amount,
        ]);
    }

    $request = new PaymentRequestData($wallet, 50000); // $500 vs mean ~$10

    $pipeline = app(SpendControlsPipeline::class);
    $decision = $pipeline->evaluate($request);

    expect($decision->isHeld())->toBeTrue()
        ->and($decision->holdType)->toBe('hold_anomaly');
    Queue::assertNotPushed(RunComplianceScreenJob::class);
});

test('short-circuits on approval hold', function (): void {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 100000]);
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'per_tx_limit_usd' => 1000,
        'daily_spend_limit_usd' => 100000,
        'daily_tx_count' => 50,
        'require_approval_above' => 50,
        'approval_timeout_secs' => 3600,
        'velocity_sensitivity' => 'medium',
        'max_new_payees_per_day' => null,
        'auto_topup' => ['enabled' => false],
    ]);
    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'amount_usd' => 100,
    ]);

    $request = new PaymentRequestData(
        walletAccount: $wallet,
        amountCents: 10000,
        payment: $payment,
    );

    $pipeline = app(SpendControlsPipeline::class);
    $decision = $pipeline->evaluate($request);

    expect($decision->isHeld())->toBeTrue()
        ->and($decision->holdType)->toBe('hold_approval')
        ->and($decision->approvalToken)->not->toBeEmpty();
    Queue::assertNotPushed(RunComplianceScreenJob::class);
});

test('approves when all gates pass and compliance runs synchronously', function (): void {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 100000]);
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'per_tx_limit_usd' => 1000,
        'require_approval_above' => 10000,
    ]);
    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'amount_usd' => 50,
    ]);

    $request = new PaymentRequestData(
        walletAccount: $wallet,
        amountCents: 5000,
        payment: $payment,
    );

    $pipeline = app(SpendControlsPipeline::class);
    $decision = $pipeline->evaluate($request);

    expect($decision->isApproved())->toBeTrue();
    Queue::assertNotPushed(RunComplianceScreenJob::class);
});
