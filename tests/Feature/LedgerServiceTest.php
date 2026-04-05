<?php

use App\Models\LedgerEntry;
use App\Models\WalletAccount;
use App\Services\Ledger\InsufficientBalanceException;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('credit creates ledger entry and updates wallet balance cache', function () {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 0]);
    $service = app(LedgerService::class);

    $entry = $service->credit(
        wallet: $wallet,
        amountCents: 5_000,
        refType: 'topup',
        refId: (string) Str::uuid(),
        description: 'Initial funding',
    );

    expect($entry)->toBeInstanceOf(LedgerEntry::class);
    expect($entry->type)->toBe('credit');
    expect((int) $entry->balance_after_cents)->toBe(5_000);
    expect((int) $wallet->fresh()->balance_cents)->toBe(5_000);
});

test('debit creates ledger entry and updates wallet balance cache', function () {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 0]);
    $service = app(LedgerService::class);

    $service->credit($wallet, 7_500, 'topup', (string) Str::uuid(), 'Seed');

    $entry = $service->debit(
        wallet: $wallet->fresh(),
        amountCents: 2_000,
        refType: 'payment',
        refId: (string) Str::uuid(),
        description: 'Agent spend',
    );

    expect($entry->type)->toBe('debit');
    expect((int) $entry->balance_after_cents)->toBe(5_500);
    expect((int) $wallet->fresh()->balance_cents)->toBe(5_500);
});

test('debit rejects insufficient balance and leaves wallet unchanged', function () {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 0]);
    $service = app(LedgerService::class);
    $service->credit($wallet, 1_000, 'topup', (string) Str::uuid(), 'Seed');

    expect(fn () => $service->debit(
        wallet: $wallet->fresh(),
        amountCents: 1_500,
        refType: 'payment',
        refId: (string) Str::uuid(),
        description: 'Too much',
    ))->toThrow(InsufficientBalanceException::class);

    expect((int) $wallet->fresh()->balance_cents)->toBe(1_000);
    expect(LedgerEntry::query()->count())->toBe(1);
});

test('sequential debits in transactions do not overdraw', function () {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 0]);
    $service = app(LedgerService::class);
    $service->credit($wallet, 1_000, 'topup', (string) Str::uuid(), 'Seed');

    DB::transaction(function () use ($service, $wallet): void {
        $service->debit($wallet->fresh(), 700, 'payment', (string) Str::uuid(), 'Debit A');
    });

    expect(fn () => DB::transaction(function () use ($service, $wallet): void {
        $service->debit($wallet->fresh(), 700, 'payment', (string) Str::uuid(), 'Debit B');
    }))->toThrow(InsufficientBalanceException::class);

    expect((int) $wallet->fresh()->balance_cents)->toBe(300);
});

test('reversal of credit creates debit entry and restores balance', function () {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 0]);
    $service = app(LedgerService::class);
    $original = $service->credit($wallet, 2_500, 'topup', (string) Str::uuid(), 'Topup');

    $reversal = $service->reversal($original, 'Chargeback');

    expect($reversal->type)->toBe('debit');
    expect((int) $reversal->amount_cents)->toBe(2_500);
    expect((int) $reversal->balance_after_cents)->toBe(0);
    expect($reversal->reference_type)->toBe('reversal');
    expect($reversal->metadata)->toMatchArray([
        'reverses_ledger_entry_id' => $original->id,
        'reverses_reference_type' => 'topup',
    ]);
    expect((int) $wallet->fresh()->balance_cents)->toBe(0);
});

test('reversal of debit creates credit entry and restores balance', function () {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 0]);
    $service = app(LedgerService::class);
    $service->credit($wallet, 5_000, 'topup', (string) Str::uuid(), 'Seed');
    $debit = $service->debit($wallet->fresh(), 2_000, 'payment', (string) Str::uuid(), 'Spend');

    $reversal = $service->reversal($debit, 'Refund');

    expect($reversal->type)->toBe('credit');
    expect((int) $reversal->balance_after_cents)->toBe(5_000);
    expect((int) $wallet->fresh()->balance_cents)->toBe(5_000);
});

test('balance for account returns sum derived balance from ledger', function () {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 0]);
    $service = app(LedgerService::class);

    $service->credit($wallet, 10_000, 'topup', (string) Str::uuid(), 'Seed');
    $service->debit($wallet->fresh(), 1_500, 'payment', (string) Str::uuid(), 'Spend 1');
    $service->debit($wallet->fresh(), 500, 'payment', (string) Str::uuid(), 'Spend 2');

    expect($service->balanceForAccount($wallet->fresh()))->toBe(8_000);
});

test('balance after chain is unbroken', function () {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 0]);
    $service = app(LedgerService::class);

    $service->credit($wallet, 1_000, 'topup', (string) Str::uuid(), 'One');
    $service->debit($wallet->fresh(), 300, 'payment', (string) Str::uuid(), 'Two');
    $service->credit($wallet->fresh(), 800, 'topup', (string) Str::uuid(), 'Three');

    $balances = LedgerEntry::query()
        ->where('wallet_account_id', $wallet->id)
        ->orderBy('id')
        ->pluck('balance_after_cents')
        ->map(fn ($value) => (int) $value)
        ->all();

    expect($balances)->toBe([1_000, 700, 1_500]);
    expect((int) $wallet->fresh()->balance_cents)->toBe(1_500);
});

test('wallet balanceUsd accessor divides cents by 100', function () {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 12345]);

    expect($wallet->balanceUsd())->toBe(123.45);
});

test('ledger reconcile command succeeds when no mismatches exist', function () {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 0]);
    $service = app(LedgerService::class);
    $service->credit($wallet, 2_000, 'topup', (string) Str::uuid(), 'Seed');

    $this->artisan('ledger:reconcile')
        ->expectsOutput('Ledger reconciliation complete: no mismatches found.')
        ->assertSuccessful();
});

test('ledger reconcile command detects intentional mismatch', function () {
    $wallet = WalletAccount::factory()->create(['balance_cents' => 0]);
    $service = app(LedgerService::class);
    $service->credit($wallet, 3_000, 'topup', (string) Str::uuid(), 'Seed');

    $wallet->forceFill(['balance_cents' => 1_111])->save();

    $this->artisan('ledger:reconcile')
        ->expectsOutputToContain('Ledger reconciliation mismatches found')
        ->assertFailed();
});
