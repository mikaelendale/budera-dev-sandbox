<?php

use App\Models\ApiKey;
use App\Models\ApprovalRequest;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Ledger\LedgerService;
use App\States\Payment\PaymentHeldApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('approve endpoint updates approval and returns success', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'balance_cents' => 0,
    ]);
    app(LedgerService::class)->credit($wallet, 1_000_000, 'manual_credit', 'api_approval_seed', 'Test opening balance');
    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'amount_usd' => 25.00,
    ]);
    $payment->status->transitionTo(PaymentHeldApproval::class);

    $token = 'tok_'.Str::random(60);
    ApprovalRequest::factory()->create([
        'approvable_type' => Payment::class,
        'approvable_id' => $payment->id,
        'requested_by_type' => User::class,
        'requested_by_id' => $owner->id,
        'approval_token' => $token,
        'expires_at' => now()->addHour(),
        'status' => 'pending',
    ]);

    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:approve'],
        'metadata' => ['key_last4' => substr($plain, -4)],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson("/api/v1/approvals/{$token}/approve")
        ->assertOk()
        ->assertJson(['status' => 'approved']);

    expect(ApprovalRequest::query()->where('approval_token', $token)->first()->status)->toBe('approved');
});

test('deny endpoint updates approval and returns success', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'balance_cents' => 1_000_000,
    ]);
    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'amount_usd' => 25.00,
    ]);
    $payment->status->transitionTo(PaymentHeldApproval::class);

    $token = 'tok_'.Str::random(60);
    ApprovalRequest::factory()->create([
        'approvable_type' => Payment::class,
        'approvable_id' => $payment->id,
        'requested_by_type' => User::class,
        'requested_by_id' => $owner->id,
        'approval_token' => $token,
        'expires_at' => now()->addHour(),
        'status' => 'pending',
    ]);

    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:approve'],
        'metadata' => ['key_last4' => substr($plain, -4)],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson("/api/v1/approvals/{$token}/deny")
        ->assertOk()
        ->assertJson(['status' => 'denied']);

    expect(ApprovalRequest::query()->where('approval_token', $token)->first()->status)->toBe('denied');
});

test('approve returns 422 when token expired', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'balance_cents' => 1_000_000,
    ]);
    $payment = Payment::factory()->create([
        'wallet_account_id' => $wallet->id,
        'amount_usd' => 25.00,
    ]);

    $token = 'tok_'.Str::random(60);
    ApprovalRequest::factory()->create([
        'approvable_type' => Payment::class,
        'approvable_id' => $payment->id,
        'approval_token' => $token,
        'expires_at' => now()->subMinute(),
        'status' => 'pending',
    ]);

    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:approve'],
        'metadata' => ['key_last4' => substr($plain, -4)],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson("/api/v1/approvals/{$token}/approve")
        ->assertUnprocessable();
});

test('approve returns 403 when token invalid', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:approve'],
        'metadata' => ['key_last4' => substr($plain, -4)],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/approvals/invalid-token-123/approve')
        ->assertForbidden();
});

test('approve returns 403 when api key lacks wallet:approve', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read'],
        'metadata' => ['key_last4' => substr($plain, -4)],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/approvals/any-token/approve')
        ->assertForbidden();
});
