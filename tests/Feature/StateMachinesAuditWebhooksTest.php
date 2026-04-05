<?php

use App\Models\AuthorizationLedgerEntry;
use App\Models\DomainAuditLog;
use App\Models\Payment;
use App\Models\StateTransition;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WebhookOutbox;
use App\Services\Audit\TransitionRecorder;
use App\States\Payment\PaymentApproved;
use App\States\Payment\PaymentProcessing;
use App\States\WalletAccount\WalletAccountActive;
use App\States\WalletAccount\WalletAccountFrozen;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

test('illegal wallet-account transition throws and records nothing', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();

    $wallet = WalletAccount::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
        'status' => 'pending',
        'partner_account_id' => 'acct_x',
        'metadata' => [],
    ]);

    expect(fn () => $wallet->status->transitionTo(WalletAccountFrozen::class))
        ->toThrow(CouldNotPerformTransition::class);

    expect(AuthorizationLedgerEntry::query()->count())->toBe(0);
    expect(DomainAuditLog::query()->count())->toBe(0);
    expect(StateTransition::query()->count())->toBe(0);
    expect(WebhookOutbox::query()->count())->toBe(0);
});

test('successful wallet-account transition records audit + state + webhook outbox', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();

    $wallet = WalletAccount::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
        'status' => 'pending',
        'partner_account_id' => 'acct_x',
        'metadata' => [],
    ]);

    $wallet->status->transitionTo(WalletAccountActive::class);

    app(TransitionRecorder::class)->record(
        $wallet,
        'pending',
        'active',
        [
            'stream' => 'agent_bank',
            'actor_type' => 'system',
            'actor_id' => null,
            'action' => 'wallet.account.activated',
            'resource_type' => 'wallet_accounts',
            'resource_id' => (string) $wallet->getKey(),
            'environment' => $wallet->environment,
            'metadata' => [
                'company_id' => (string) $company->getKey(),
            ],
            'webhook_event' => 'account.active',
            'webhook_payload' => [
                'event' => 'account.active',
                'data' => [
                    'wallet_account_id' => (string) $wallet->getKey(),
                ],
            ],
        ],
    );

    $authorizationLedger = AuthorizationLedgerEntry::query()->first();
    expect($authorizationLedger)->not->toBeNull()
        ->and($authorizationLedger->authorization_signature)->not->toBeNull()
        ->and($authorizationLedger->authorization_signature)->not->toBe('');

    expect(DomainAuditLog::query()->count())->toBe(1);
    expect(StateTransition::query()->count())->toBe(1);

    $outbox = WebhookOutbox::query()->first();
    expect($outbox)->not->toBeNull();
    expect($outbox->event)->toBe('account.active');
});

test('payment illegal transition throws and successful transition records audit + outbox', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();

    $wallet = WalletAccount::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'environment' => 'sandbox',
        'status' => 'pending',
        'partner_account_id' => 'acct_x',
        'metadata' => [],
    ]);

    $payment = Payment::query()->create([
        'wallet_account_id' => $wallet->getKey(),
        'environment' => 'sandbox',
        'status' => 'pending',
        'amount_usd' => 12.34,
        'metadata' => [],
    ]);

    expect(fn () => $payment->status->transitionTo(PaymentProcessing::class))
        ->toThrow(CouldNotPerformTransition::class);

    $payment->status->transitionTo(PaymentApproved::class);

    app(TransitionRecorder::class)->record(
        $payment,
        'pending',
        'approved',
        [
            'stream' => 'agent_bank',
            'actor_type' => 'system',
            'actor_id' => null,
            'action' => 'payment.approved',
            'resource_type' => 'payments',
            'resource_id' => (string) $payment->getKey(),
            'environment' => $payment->environment,
            'metadata' => [
                'company_id' => (string) $company->getKey(),
            ],
            'webhook_event' => 'payment.approved',
            'webhook_payload' => [
                'event' => 'payment.approved',
                'data' => [
                    'payment_id' => (string) $payment->getKey(),
                ],
            ],
        ],
    );

    expect(AuthorizationLedgerEntry::query()->count())->toBe(1);
    expect(DomainAuditLog::query()->count())->toBe(1);
    expect(StateTransition::query()->count())->toBe(1);

    expect(WebhookOutbox::query()->where('event', 'payment.approved')->exists())->toBeTrue();
});
