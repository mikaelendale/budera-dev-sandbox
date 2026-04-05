<?php

use App\Models\Policy;
use App\Models\WalletAccount;
use App\Services\SpendControls\BalanceGate;
use App\Services\SpendControls\Data\PaymentRequestData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('approves when balance sufficient', function (): void {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 10000]);
    $request = new PaymentRequestData($wallet, 5000);

    $decision = (new BalanceGate)->evaluate($request);

    expect($decision->isApproved())->toBeTrue();
});

test('holds needs_topup when insufficient and auto_topup enabled', function (): void {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 100]);
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'auto_topup' => ['enabled' => true, 'threshold' => 500, 'amount' => 1000],
    ]);

    $request = new PaymentRequestData($wallet, 5000);

    $decision = (new BalanceGate)->evaluate($request);

    expect($decision->isHeld())->toBeTrue()
        ->and($decision->holdType)->toBe('needs_topup');
});

test('rejects when insufficient and auto_topup disabled', function (): void {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 100]);
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'auto_topup' => ['enabled' => false],
    ]);

    $request = new PaymentRequestData($wallet, 5000);

    $decision = (new BalanceGate)->evaluate($request);

    expect($decision->isRejected())->toBeTrue()
        ->and($decision->reasonCode)->toBe('insufficient_balance');
});

test('rejects when insufficient and no policy', function (): void {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 100]);

    $request = new PaymentRequestData($wallet, 5000);

    $decision = (new BalanceGate)->evaluate($request);

    expect($decision->isRejected())->toBeTrue()
        ->and($decision->reasonCode)->toBe('insufficient_balance');
});
