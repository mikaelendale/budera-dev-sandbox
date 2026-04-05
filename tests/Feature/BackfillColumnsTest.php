<?php

use App\Models\ApiKey;
use App\Models\AuthorizationLedgerEntry;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\IdempotencyKey;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Policy;
use App\Models\Topup;
use App\Models\Transfer;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletOauthGrant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookOutbox;
use App\Tenancy\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('payments table has direction, rail, payee_ref, idempotency_key, held_reason, settled_at columns', function () {
    $payment = Payment::factory()->create([
        'direction' => 'outbound',
        'rail' => 'ach',
        'payee_ref' => 'vendor-123',
        'idempotency_key' => 'idem-pay-001',
        'held_reason' => 'approval_required',
        'settled_at' => now(),
    ]);

    expect($payment->direction)->toBe('outbound');
    expect($payment->rail)->toBe('ach');
    expect($payment->payee_ref)->toBe('vendor-123');
    expect($payment->idempotency_key)->toBe('idem-pay-001');
    expect($payment->held_reason)->toBe('approval_required');
    expect($payment->settled_at)->not()->toBeNull();
});

test('topups table has bank_link_id, idempotency_key, settled_at columns', function () {
    $bankLink = BankLink::factory()->verified()->create();
    $wallet = WalletAccount::factory()->create();

    $topup = Topup::factory()->create([
        'wallet_account_id' => $wallet->id,
        'bank_link_id' => $bankLink->id,
        'idempotency_key' => 'idem-top-001',
        'settled_at' => now(),
    ]);

    expect($topup->bank_link_id)->toBe($bankLink->id);
    expect($topup->bankLink)->toBeInstanceOf(BankLink::class);
    expect($topup->idempotency_key)->toBe('idem-top-001');
    expect($topup->settled_at)->not()->toBeNull();
});

test('transfers table has idempotency_key column', function () {
    $transfer = Transfer::factory()->create([
        'idempotency_key' => 'idem-txfr-001',
    ]);

    expect($transfer->idempotency_key)->toBe('idem-txfr-001');
});

test('companies table has email, kyb_status, sandbox_limit_overrides columns', function () {
    $company = Company::factory()->create([
        'email' => 'ops@acme.com',
        'kyb_status' => 'pending',
        'sandbox_limit_overrides' => ['max_wallets' => 100],
    ]);

    expect($company->email)->toBe('ops@acme.com');
    expect($company->kyb_status)->toBe('pending');
    expect($company->sandbox_limit_overrides)->toBe(['max_wallets' => 100]);
});

test('wallet_accounts table has agent_id and balance_cents columns', function () {
    $wallet = WalletAccount::factory()->forAgent('agent_travel_bot')->create([
        'balance_cents' => 150000,
    ]);

    expect($wallet->agent_id)->toBe('agent_travel_bot');
    expect($wallet->balance_cents)->toBe(150000);
});

test('policies table has agent_type column', function () {
    $policy = Policy::factory()->create([
        'agent_type' => 'travel_booking',
    ]);

    expect($policy->agent_type)->toBe('travel_booking');
});

test('bank_links table has encrypted routing/account and failed attempts', function () {
    $bankLink = BankLink::factory()->create([
        'encrypted_routing' => '021000021',
        'encrypted_account' => '123456789012',
        'failed_verification_attempts' => 2,
    ]);

    $fresh = BankLink::withoutGlobalScope('company')->find($bankLink->id);

    expect($fresh->encrypted_routing)->toBe('021000021');
    expect($fresh->encrypted_account)->toBe('123456789012');
    expect($fresh->failed_verification_attempts)->toBe(2);
});

test('authorization_ledger has ip_address, user_agent, account_id columns', function () {
    $entry = AuthorizationLedgerEntry::query()->create([
        'stream' => 'bank_link_verify',
        'actor_type' => 'user',
        'actor_id' => '1',
        'authorization_text' => 'User authorized bank link',
        'authorization_hash' => hash('sha256', 'test'),
        'authorization_signature' => 'sig_test',
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Mozilla/5.0',
        'account_id' => 42,
        'correlation_id' => 'corr-123',
        'environment' => 'sandbox',
        'metadata' => [],
    ]);

    expect($entry->ip_address)->toBe('192.168.1.1');
    expect($entry->user_agent)->toBe('Mozilla/5.0');
    expect($entry->account_id)->toBe(42);
});

test('webhook_outbox uses company_id FK instead of json scoping', function () {
    $company = Company::factory()->create();

    $outbox = WebhookOutbox::factory()->create([
        'company_id' => $company->id,
    ]);

    expect($outbox->company_id)->toBe($company->id);
    expect($outbox->company)->toBeInstanceOf(Company::class);

    $companyB = Company::factory()->create();
    WebhookOutbox::factory()->create(['company_id' => $companyB->id]);

    app()->instance(CompanyContext::class, new CompanyContext(companyId: $company->id));

    expect(WebhookOutbox::query()->count())->toBe(1);
    expect(WebhookOutbox::query()->first()->id)->toBe($outbox->id);
});

test('api_keys has owner_id and label columns', function () {
    $owner = User::factory()->create();

    $key = ApiKey::factory()->create([
        'owner_id' => $owner->id,
        'label' => 'Production key',
    ]);

    expect($key->owner)->toBeInstanceOf(User::class);
    expect($key->label)->toBe('Production key');
});

test('public IDs are auto-generated with correct prefixes on creation', function () {
    $wallet = WalletAccount::factory()->create();
    $payment = Payment::factory()->create(['wallet_account_id' => $wallet->id]);
    $topup = Topup::factory()->create(['wallet_account_id' => $wallet->id]);
    $transfer = Transfer::factory()->create();
    $bankLink = BankLink::factory()->create();
    $apiKey = ApiKey::factory()->create();

    expect($wallet->public_id)->toStartWith('act_');
    expect($payment->public_id)->toStartWith('pay_');
    expect($topup->public_id)->toStartWith('top_');
    expect($transfer->public_id)->toStartWith('txfr_');
    expect($bankLink->public_id)->toStartWith('bl_');
    expect($apiKey->public_id)->toStartWith('key_');

    expect(strlen($wallet->public_id))->toBeGreaterThan(10);
});

test('transfer scoping includes both from and to wallet account directions', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $walletA = WalletAccount::factory()->create(['company_id' => $companyA->id]);
    $walletB = WalletAccount::factory()->create(['company_id' => $companyB->id]);

    $outgoing = Transfer::factory()->create([
        'from_wallet_account_id' => $walletA->id,
        'to_wallet_account_id' => $walletB->id,
    ]);
    $incoming = Transfer::factory()->create([
        'from_wallet_account_id' => $walletB->id,
        'to_wallet_account_id' => $walletA->id,
    ]);
    $unrelated = Transfer::factory()->create();

    app()->instance(CompanyContext::class, new CompanyContext(companyId: $companyA->id));

    $visible = Transfer::query()->pluck('id')->sort()->values()->all();
    $expected = collect([$outgoing->id, $incoming->id])->sort()->values()->all();

    expect($visible)->toBe($expected);
});

test('all new factories create valid models', function () {
    expect(LedgerEntry::factory()->credit()->create())->toBeInstanceOf(LedgerEntry::class);
    expect(IdempotencyKey::factory()->create())->toBeInstanceOf(IdempotencyKey::class);
    expect(WebhookEndpoint::factory()->create())->toBeInstanceOf(WebhookEndpoint::class);
    expect(WebhookDelivery::factory()->create())->toBeInstanceOf(WebhookDelivery::class);
    expect(CompanyInvitation::factory()->create())->toBeInstanceOf(CompanyInvitation::class);
    expect(WalletOauthGrant::factory()->create())->toBeInstanceOf(WalletOauthGrant::class);
    expect(WebhookOutbox::factory()->create())->toBeInstanceOf(WebhookOutbox::class);
});

test('bank link has topups relationship', function () {
    $bankLink = BankLink::factory()->verified()->create();
    $wallet = WalletAccount::factory()->create();

    Topup::factory()->create([
        'wallet_account_id' => $wallet->id,
        'bank_link_id' => $bankLink->id,
    ]);

    expect($bankLink->topups()->count())->toBe(1);
});

test('payment has compliance flags and approval requests relationships', function () {
    $payment = Payment::factory()->create();

    expect($payment->complianceFlags())->not()->toBeNull();
    expect($payment->approvalRequests())->not()->toBeNull();
});
