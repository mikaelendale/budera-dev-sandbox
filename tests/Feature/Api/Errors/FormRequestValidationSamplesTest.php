<?php

use App\Models\ApiKey;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Support\Str;

function validationSamplesApiKey(User $owner, array $abilities): string
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

test('store payment request validation exposes amount_cents', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = validationSamplesApiKey($owner, ['wallet:pay', 'wallet:read']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => Company::query()->where('owner_id', $owner->id)->value('id'),
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_val_pay',
            'balance_cents' => 1000,
        ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_val_pay_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/payments', [
        'wallet_account_id' => $wallet->public_id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount_cents']);
});

test('store topup request validation exposes bank_link_id', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = validationSamplesApiKey($owner, ['wallet:topup', 'wallet:read']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => Company::query()->where('owner_id', $owner->id)->value('id'),
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_val_top',
        ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_val_top_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/topups', [
        'wallet_account_id' => $wallet->public_id,
        'amount_cents' => 100,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['bank_link_id']);
});

test('store transfer request validation exposes to_wallet_account_id', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = validationSamplesApiKey($owner, ['wallet:transfer', 'wallet:read']);

    $from = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => Company::query()->where('owner_id', $owner->id)->value('id'),
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_val_from',
        ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_val_tr_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/transfers', [
        'from_wallet_account_id' => $from->public_id,
        'amount_cents' => 50,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['to_wallet_account_id']);
});

test('verify bank link request validation exposes amount_first_cents', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = validationSamplesApiKey($owner, ['wallet:link']);

    $link = BankLink::factory()
        ->microdepositSent()
        ->create([
            'user_id' => $owner->id,
            'environment' => 'sandbox',
        ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson("/api/v1/bank-links/{$link->public_id}/verify", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount_first_cents']);
});

test('simulation settlement request validation exposes bank_transfer_id', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = validationSamplesApiKey($owner, ['sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/settlement', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['bank_transfer_id']);
});
