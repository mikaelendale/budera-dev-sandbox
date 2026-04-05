<?php

use App\Models\Payment;
use App\Models\Topup;
use App\Models\WalletAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Str;

test('transfer ach settled webhook debits ledger for outbound payment', function (): void {
    config(['services.mock_bank.webhook_secret' => 'whsec_test']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'partner_account_id' => 'acct_webhook_pay',
            'balance_cents' => 50_000,
        ]);

    app(LedgerService::class)->credit($wallet, 50_000, 'seed', (string) Str::uuid(), 'Test seed');

    $payment = Payment::factory()
        ->processing()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'amount_usd' => 25.00,
            'metadata' => ['bank_transfer_id' => 'trf_wh_pay_1'],
        ]);

    $payload = [
        'event' => 'transfer.ach.settled',
        'data' => [
            'transfer_id' => 'trf_wh_pay_1',
            'direction' => 'credit',
            'amount_cents' => 2500,
            'rail' => 'ach',
        ],
    ];

    $raw = json_encode($payload);
    $sig = hash_hmac('sha256', (string) $raw, 'whsec_test');

    $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => 'sha256='.$sig,
    ], $raw)->assertOk();

    $freshPayment = $payment->fresh();
    expect($freshPayment->status->getValue())->toBe('settled')
        ->and($freshPayment->metadata['settlement_ledger_entry_id'] ?? null)->not->toBeNull()
        ->and((int) $wallet->fresh()->balance_cents)->toBe(47_500);
});

test('transfer ach settled webhook credits ledger for inbound topup', function (): void {
    config(['services.mock_bank.webhook_secret' => 'whsec_test']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'partner_account_id' => 'acct_webhook_top',
            'balance_cents' => 1_000,
        ]);

    app(LedgerService::class)->credit($wallet, 1_000, 'seed', (string) Str::uuid(), 'Test seed');

    $topup = Topup::factory()
        ->processing()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'amount_usd' => 40.00,
            'metadata' => ['bank_transfer_id' => 'trf_wh_top_1'],
        ]);

    $payload = [
        'event' => 'transfer.ach.settled',
        'data' => [
            'transfer_id' => 'trf_wh_top_1',
            'direction' => 'debit',
            'amount_cents' => 4000,
            'rail' => 'ach',
        ],
    ];

    $raw = json_encode($payload);
    $sig = hash_hmac('sha256', (string) $raw, 'whsec_test');

    $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => 'sha256='.$sig,
    ], $raw)->assertOk();

    expect($topup->fresh()->status->getValue())->toBe('settled')
        ->and((int) $wallet->fresh()->balance_cents)->toBe(5_000);
});

test('transfer ach returned webhook reverses payment settlement and marks returned', function (): void {
    config(['services.mock_bank.webhook_secret' => 'whsec_test']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'partner_account_id' => 'acct_webhook_ret',
            'balance_cents' => 50_000,
        ]);

    app(LedgerService::class)->credit($wallet, 50_000, 'seed', (string) Str::uuid(), 'Test seed');

    $entry = app(LedgerService::class)->debit(
        $wallet,
        2_500,
        'payment',
        'pay_ret_pub',
        'Outbound ACH settled',
    );

    $payment = Payment::factory()
        ->settled()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'amount_usd' => 25.00,
            'metadata' => [
                'bank_transfer_id' => 'trf_wh_ret_1',
                'settlement_ledger_entry_id' => $entry->getKey(),
            ],
            'settled_at' => now(),
        ]);

    $payload = [
        'event' => 'transfer.ach.returned',
        'data' => [
            'transfer_id' => 'trf_wh_ret_1',
            'direction' => 'credit',
            'amount_cents' => 2500,
            'rail' => 'ach',
        ],
    ];

    $raw = json_encode($payload);
    $sig = hash_hmac('sha256', (string) $raw, 'whsec_test');

    $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => 'sha256='.$sig,
    ], $raw)->assertOk();

    expect($payment->fresh()->status->getValue())->toBe('returned')
        ->and((int) $wallet->fresh()->balance_cents)->toBe(50_000);
});
