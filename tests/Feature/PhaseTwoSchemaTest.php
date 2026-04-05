<?php

use App\Models\ApprovalRequest;
use App\Models\Company;
use App\Models\ComplianceFlag;
use App\Models\IdempotencyKey;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Policy;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('policy model and factory cast json fields correctly', function () {
    $policy = Policy::factory()->create();

    expect($policy->walletAccount)->toBeInstanceOf(WalletAccount::class);
    expect($policy->allowed_categories)->toBeArray();
    expect($policy->blocked_payees)->toBeArray();
    expect($policy->auto_topup)->toBeArray();
    expect($policy->walletAccount->policy)->toBeInstanceOf(Policy::class);
});

test('ledger entries are append only and wallet balanceCents reads latest balance', function () {
    $wallet = WalletAccount::factory()->create();

    $firstEntry = LedgerEntry::query()->create([
        'wallet_account_id' => $wallet->id,
        'type' => 'credit',
        'amount_cents' => 1500,
        'reference_type' => 'topup',
        'reference_id' => (string) str()->uuid(),
        'balance_after_cents' => 1500,
        'description' => 'Initial topup',
        'metadata' => [],
        'created_at' => now()->subSecond(),
    ]);

    LedgerEntry::query()->create([
        'wallet_account_id' => $wallet->id,
        'type' => 'debit',
        'amount_cents' => 250,
        'reference_type' => 'payment',
        'reference_id' => (string) str()->uuid(),
        'balance_after_cents' => 1250,
        'description' => 'Payment',
        'metadata' => [],
        'created_at' => now(),
    ]);

    expect($wallet->fresh()->computedBalanceCents())->toBe(1250);

    expect(function () use ($firstEntry): void {
        $firstEntry->description = 'Edited';
        $firstEntry->save();
    })->toThrow(LogicException::class);

    expect(fn () => $firstEntry->delete())->toThrow(LogicException::class);
});

test('idempotency key model works with unique key per company', function () {
    $company = Company::factory()->create();

    $idempotencyKey = IdempotencyKey::query()->create([
        'key' => 'abc-123',
        'company_id' => $company->id,
        'request_hash' => hash('sha256', '{"amount":100}'),
        'response_status' => 200,
        'response_body' => ['ok' => true],
        'created_at' => now(),
    ]);

    expect($idempotencyKey->response_body)->toBeArray();
    expect($idempotencyKey->company)->toBeInstanceOf(Company::class);

    expect(function () use ($company): void {
        IdempotencyKey::query()->create([
            'key' => 'abc-123',
            'company_id' => $company->id,
            'request_hash' => hash('sha256', '{"amount":100}'),
            'response_status' => 200,
            'response_body' => ['ok' => true],
            'created_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});

test('webhook endpoints and deliveries models work with relationships and encrypted secrets', function () {
    $company = Company::factory()->create();

    $endpoint = WebhookEndpoint::query()->create([
        'company_id' => $company->id,
        'url' => 'https://example.com/webhook',
        'secret' => 'super-secret',
        'events' => ['payment.settled'],
        'environment' => 'sandbox',
        'is_active' => true,
    ]);

    $delivery = WebhookDelivery::query()->create([
        'webhook_endpoint_id' => $endpoint->id,
        'event' => 'payment.settled',
        'event_id' => 'evt_1',
        'payload' => ['id' => 'evt_1'],
        'status' => 'queued',
        'attempts' => 0,
    ]);

    expect($endpoint->company)->toBeInstanceOf(Company::class);
    expect($endpoint->deliveries()->count())->toBe(1);
    expect($delivery->webhookEndpoint)->toBeInstanceOf(WebhookEndpoint::class);

    $rawSecret = DB::table('webhook_endpoints')
        ->where('id', $endpoint->id)
        ->value('secret');

    expect($rawSecret)->not()->toBe('super-secret');
});

test('compliance flags and approval requests models and factories work', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->create();

    $flag = ComplianceFlag::factory()->create([
        'flaggable_type' => Payment::class,
        'flaggable_id' => $payment->id,
        'resolved_by' => $user->id,
        'resolved_at' => now(),
    ]);

    $approval = ApprovalRequest::factory()->create([
        'approvable_type' => Payment::class,
        'approvable_id' => $payment->id,
        'requested_by_type' => User::class,
        'requested_by_id' => $user->id,
        'decided_by' => $user->id,
        'decided_at' => now(),
        'status' => 'approved',
    ]);

    expect($flag->flaggable)->toBeInstanceOf(Payment::class);
    expect($flag->resolvedBy)->toBeInstanceOf(User::class);
    expect($approval->approvable)->toBeInstanceOf(Payment::class);
    expect($approval->requestedBy)->toBeInstanceOf(User::class);
    expect($approval->decidedBy)->toBeInstanceOf(User::class);
});
