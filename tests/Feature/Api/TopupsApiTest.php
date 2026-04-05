<?php

use App\Models\ApiKey;
use App\Models\AuthorizationLedgerEntry;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\Topup;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'test-secret',
    ]);

    Http::fake([
        'http://mock-bank.test/*' => Http::response([
            'transfer_id' => 'trf_top_1',
            'ref' => 'trf_top_1',
            'rail' => 'ach',
            'status' => 'pending',
            'duplicate' => false,
        ], 202),
    ]);
});

function topupApiKey(User $owner, array $abilities): string
{
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => $abilities,
        'metadata' => [],
    ]);

    return $plain;
}

test('post topups requires verified bank link', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = topupApiKey($owner, ['wallet:topup', 'wallet:read']);

    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_acct_top',
            'balance_cents' => 0,
        ]);

    $bankLink = BankLink::factory()->verified()->withAchStandingConsent()->create([
        'user_id' => $owner->id,
        'environment' => 'sandbox',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_top_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/topups', [
        'wallet_account_id' => $wallet->public_id,
        'bank_link_id' => $bankLink->public_id,
        'amount_cents' => 5_000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'processing');

    $topupPublicId = $response->json('data.id');
    expect(is_string($topupPublicId))->toBeTrue();

    $topupRow = Topup::query()->where('public_id', $topupPublicId)->firstOrFail();
    expect($topupRow->authorization_ledger_entry_id)->not->toBeNull();
});

test('post topups accepts explicit authorization_ledger_entry_id', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = topupApiKey($owner, ['wallet:topup', 'wallet:read']);

    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_acct_top_explicit',
            'balance_cents' => 0,
        ]);

    $bankLink = BankLink::factory()->verified()->withAchStandingConsent()->create([
        'user_id' => $owner->id,
        'environment' => 'sandbox',
    ]);

    $ledgerId = AuthorizationLedgerEntry::query()
        ->where('metadata->record_kind', 'ach_standing_consent')
        ->where('metadata->bank_link_id', (string) $bankLink->getKey())
        ->orderByDesc('id')
        ->value('id');
    expect($ledgerId)->not->toBeNull();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_top_explicit_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/topups', [
        'wallet_account_id' => $wallet->public_id,
        'bank_link_id' => $bankLink->public_id,
        'amount_cents' => 2_500,
        'authorization_ledger_entry_id' => (int) $ledgerId,
    ]);

    $response->assertCreated();

    $topupPublicId = $response->json('data.id');
    $topupRow = Topup::query()->where('public_id', $topupPublicId)->firstOrFail();
    expect($topupRow->authorization_ledger_entry_id)->toBe((int) $ledgerId);
});

test('post topups rejects revoked bank link', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = topupApiKey($owner, ['wallet:topup', 'wallet:read']);

    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_acct_top_revoked',
            'balance_cents' => 0,
        ]);

    $bankLink = BankLink::factory()->revoked()->create([
        'user_id' => $owner->id,
        'environment' => 'sandbox',
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_top_revoked_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/topups', [
        'wallet_account_id' => $wallet->public_id,
        'bank_link_id' => $bankLink->public_id,
        'amount_cents' => 5_000,
    ])
        ->assertStatus(422);
});

test('post topups rejects unverified bank link', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = topupApiKey($owner, ['wallet:topup', 'wallet:read']);

    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_acct_top2',
            'balance_cents' => 0,
        ]);

    $bankLink = BankLink::factory()->create([
        'user_id' => $owner->id,
        'environment' => 'sandbox',
        'status' => 'initiated',
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_top_unverified_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/topups', [
        'wallet_account_id' => $wallet->public_id,
        'bank_link_id' => $bankLink->public_id,
        'amount_cents' => 5_000,
    ])
        ->assertStatus(422);
});
