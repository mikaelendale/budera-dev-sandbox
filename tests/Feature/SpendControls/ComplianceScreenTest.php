<?php

use App\Models\ComplianceFlag;
use App\Models\Payment;
use App\Models\WalletAccount;
use App\Services\SpendControls\ComplianceScreen;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creates ofac flag when payee matches blocked pattern', function (): void {
    $wallet = WalletAccount::factory()->create();
    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'payee_ref' => 'vendor-ofac_blocked-123',
    ]);

    (new ComplianceScreen)->run($payment);

    $flag = ComplianceFlag::query()
        ->where('flaggable_type', Payment::class)
        ->where('flaggable_id', $payment->id)
        ->where('flag_type', 'ofac')
        ->first();

    expect($flag)->not->toBeNull()
        ->and($flag->severity)->toBe('critical');
});

test('creates high_risk_payee flag when payee matches', function (): void {
    $wallet = WalletAccount::factory()->create();
    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'payee_ref' => 'recipient-high_risk-xyz',
    ]);

    (new ComplianceScreen)->run($payment);

    $flag = ComplianceFlag::query()
        ->where('flaggable_type', Payment::class)
        ->where('flaggable_id', $payment->id)
        ->where('flag_type', 'high_risk_payee')
        ->first();

    expect($flag)->not->toBeNull()
        ->and($flag->severity)->toBe('high');
});

test('creates structuring flag when similar amounts in short window', function (): void {
    $wallet = WalletAccount::factory()->create();
    $amount = 99.50;

    Payment::factory()->count(2)->create([
        'wallet_account_id' => $wallet->id,
        'amount_usd' => $amount,
    ]);

    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'amount_usd' => $amount + 0.50,
    ]);

    (new ComplianceScreen)->run($payment);

    $flag = ComplianceFlag::query()
        ->where('flaggable_type', Payment::class)
        ->where('flaggable_id', $payment->id)
        ->where('flag_type', 'structuring')
        ->first();

    expect($flag)->not->toBeNull()
        ->and($flag->severity)->toBe('high');
});

test('does not create flag for clean payee', function (): void {
    $wallet = WalletAccount::factory()->create();
    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'payee_ref' => 'legitimate-vendor',
    ]);

    (new ComplianceScreen)->run($payment);

    $count = ComplianceFlag::query()
        ->where('flaggable_type', Payment::class)
        ->where('flaggable_id', $payment->id)
        ->count();

    expect($count)->toBe(0);
});
