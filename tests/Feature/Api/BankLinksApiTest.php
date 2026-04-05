<?php

use App\Models\ApiKey;
use App\Models\AuthorizationLedgerEntry;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Audit\CryptoSigner;
use App\States\BankLink\BankLinkFailed;
use App\States\BankLink\BankLinkRevoked;
use App\States\BankLink\BankLinkVerified;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function bankLinkTestApiKey(User $owner, array $abilities): string
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

test('bank link create verify and show', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = bankLinkTestApiKey($owner, ['wallet:link', 'wallet:read']);

    $create = $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'routing_number' => '021000021',
            'account_number' => '123456789012',
            'bank_slug' => 'chase',
        ]);

    $create->assertCreated()
        ->assertJsonPath('data.status', 'microdeposit_sent');

    $publicId = $create->json('data.id');
    expect(is_string($publicId))->toBeTrue();

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->getJson("/api/v1/bank-links/{$publicId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'microdeposit_sent');

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson("/api/v1/bank-links/{$publicId}/verify", [
            'amount_first_cents' => 34,
            'amount_second_cents' => 12,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'verified');

    $link = BankLink::query()->where('public_id', $publicId)->firstOrFail();
    expect($link->status)->toBeInstanceOf(BankLinkVerified::class);

    $ledger = AuthorizationLedgerEntry::query()
        ->where('metadata->record_kind', 'ach_standing_consent')
        ->where('metadata->bank_link_id', (string) $link->getKey())
        ->orderByDesc('id')
        ->first();

    expect($ledger)->not->toBeNull();
    expect(app(CryptoSigner::class)->verifySignature(
        (string) $ledger->authorization_text,
        (string) $ledger->authorization_signature,
    ))->toBeTrue();
});

test('bank link verify wrong amounts increments attempts and third locks out', function (): void {
    $owner = User::factory()->withCompany('Acme')->create();
    $plain = bankLinkTestApiKey($owner, ['wallet:link', 'wallet:read']);

    $create = $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'routing_number' => '021000021',
            'account_number' => '987654321098',
        ]);

    $create->assertCreated();
    $publicId = $create->json('data.id');

    for ($i = 0; $i < 2; $i++) {
        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson("/api/v1/bank-links/{$publicId}/verify", [
                'amount_first_cents' => 99,
                'amount_second_cents' => 98,
            ])
            ->assertStatus(422);
    }

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson("/api/v1/bank-links/{$publicId}/verify", [
            'amount_first_cents' => 1,
            'amount_second_cents' => 2,
        ])
        ->assertStatus(422);

    $link = BankLink::query()->where('public_id', $publicId)->firstOrFail();
    expect($link->status)->toBeInstanceOf(BankLinkFailed::class)
        ->and($link->failed_verification_attempts)->toBe(3);
});

test('revoked bank link blocks topups', function (): void {
    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'test-secret',
    ]);

    Http::fake([
        'http://mock-bank.test/*' => Http::response([
            'transfer_id' => 'trf_top_bl',
            'ref' => 'trf_top_bl',
            'rail' => 'ach',
            'status' => 'pending',
            'duplicate' => false,
        ], 202),
    ]);

    $owner = User::factory()->withCompany('Acme')->create();
    $plainLink = bankLinkTestApiKey($owner, ['wallet:link', 'wallet:read', 'wallet:topup']);
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $create = $this->withHeader('Authorization', 'Bearer '.$plainLink)
        ->postJson('/api/v1/bank-links', [
            'routing_number' => '021000021',
            'account_number' => '111122223333',
        ]);
    $create->assertCreated();
    $publicId = $create->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$plainLink)
        ->postJson("/api/v1/bank-links/{$publicId}/verify", [
            'amount_first_cents' => 12,
            'amount_second_cents' => 34,
        ])
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer '.$plainLink)
        ->deleteJson("/api/v1/bank-links/{$publicId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'revoked');

    $link = BankLink::query()->where('public_id', $publicId)->firstOrFail();
    expect($link->status)->toBeInstanceOf(BankLinkRevoked::class);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_acct_bl',
            'balance_cents' => 0,
        ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plainLink,
        'Idempotency-Key' => 'idem_top_bl_'.Str::uuid()->toString(),
    ])->postJson('/api/v1/topups', [
        'wallet_account_id' => $wallet->public_id,
        'bank_link_id' => $publicId,
        'amount_cents' => 1_000,
    ])->assertStatus(422);
});
