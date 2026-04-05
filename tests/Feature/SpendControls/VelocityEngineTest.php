<?php

use App\Models\Payment;
use App\Models\Policy;
use App\Models\WalletAccount;
use App\Services\SpendControls\Data\PaymentRequestData;
use App\Services\SpendControls\VelocityEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('approves when no 24h activity and normal request', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'velocity_sensitivity' => 'medium',
    ]);

    $request = new PaymentRequestData($wallet, 10000);

    $decision = (new VelocityEngine)->evaluate($request);

    expect($decision->isApproved())->toBeTrue();
});

test('holds anomaly when tx count exceeds baseline for sensitivity', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'velocity_sensitivity' => 'high', // 5 per hour * 24 = 120 max
        'max_new_payees_per_day' => null,
    ]);

    Payment::factory()->count(120)->create(['wallet_account_id' => $wallet->id]);

    $request = new PaymentRequestData($wallet, 1000);

    $decision = (new VelocityEngine)->evaluate($request);

    expect($decision->isHeld())->toBeTrue()
        ->and($decision->holdType)->toBe('hold_anomaly');
});

test('holds anomaly when amount deviates from rolling mean', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'velocity_sensitivity' => 'medium',
        'max_new_payees_per_day' => null,
    ]);

    foreach ([10, 11, 9, 10, 12] as $amount) {
        Payment::factory()->create([
            'wallet_account_id' => $wallet->id,
            'amount_usd' => $amount,
        ]);
    }

    $request = new PaymentRequestData($wallet, 50000); // $500 vs mean ~$10

    $decision = (new VelocityEngine)->evaluate($request);

    expect($decision->isHeld())->toBeTrue()
        ->and($decision->holdType)->toBe('hold_anomaly');
});

test('holds anomaly when max_new_payees_per_day exceeded in 24h', function (): void {
    $wallet = WalletAccount::factory()->create();
    Policy::factory()->create([
        'wallet_account_id' => $wallet->id,
        'velocity_sensitivity' => 'low',
        'max_new_payees_per_day' => 1,
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

    $decision = (new VelocityEngine)->evaluate($request);

    expect($decision->isHeld())->toBeTrue()
        ->and($decision->holdType)->toBe('hold_anomaly');
});
