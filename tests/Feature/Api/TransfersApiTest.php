<?php

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Str;

function transferApiKey(User $owner, array $abilities): string
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

test('post transfers moves balance between company wallets', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = transferApiKey($owner, ['wallet:transfer', 'wallet:read']);

    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $from = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_from',
            'balance_cents' => 10_000,
        ]);

    app(LedgerService::class)->credit($from, 10_000, 'seed', (string) Str::uuid(), 'Test seed');

    $to = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_to',
            'balance_cents' => 0,
        ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_txfr_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/transfers', [
        'from_wallet_account_id' => $from->public_id,
        'to_wallet_account_id' => $to->public_id,
        'amount_cents' => 3_000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'completed');

    expect((int) $from->fresh()->balance_cents)->toBe(7_000)
        ->and((int) $to->fresh()->balance_cents)->toBe(3_000);
});
