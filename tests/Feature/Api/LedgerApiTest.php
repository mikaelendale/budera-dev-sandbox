<?php

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Str;

function ledgerApiKey(User $owner, array $abilities): string
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

test('get wallet ledger returns entries', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = ledgerApiKey($owner, ['wallet:read']);

    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_ledger',
            'balance_cents' => 0,
        ]);

    $ledger = app(LedgerService::class);
    $ledger->credit($wallet, 1_500, 'test', (string) Str::uuid(), 'seed');

    $response = $this->withHeader('Authorization', 'Bearer '.$plain)
        ->getJson('/api/v1/wallets/'.$wallet->public_id.'/ledger');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.amount_cents'))->toBe(1500);
});

test('ledger endpoint requires read ability', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = ledgerApiKey($owner, ['wallet:pay']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => Company::query()->where('owner_id', $owner->id)->value('id'),
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_ledger2',
            'balance_cents' => 0,
        ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->getJson('/api/v1/wallets/'.$wallet->public_id.'/ledger')
        ->assertForbidden();
});
