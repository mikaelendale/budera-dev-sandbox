<?php

use App\Models\ApiKey;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Topup;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use App\States\WalletKycVerification\WalletKycVerificationPending;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

function simulationTestApiKey(User $owner, string $environment, array $abilities): string
{
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $plain = ($environment === 'live' ? 'sk_live_' : 'sk_sandbox_').Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => $environment,
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => $abilities,
        'metadata' => [],
    ]);

    return $plain;
}

test('sandbox simulation routes return 404 when app environment is production', function (): void {
    $prev = config('app.env');
    config(['app.env' => 'production']);

    try {
        $owner = User::factory()->withCompany()->create();
        $plain = simulationTestApiKey($owner, 'sandbox', ['wallet:read', 'sandbox:simulate']);

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_x'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'sandbox_disabled_production');
    } finally {
        config(['app.env' => $prev]);
    }
});

test('sandbox simulation routes reject live api keys', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = simulationTestApiKey($owner, 'live', ['wallet:read', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_x'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'simulation_forbidden_live_environment');
});

test('sandbox simulation routes reject oauth token without api key', function (): void {
    $user = User::factory()->withCompany()->create();
    Passport::actingAs($user, ['sandbox:simulate', 'wallet:pay']);

    $this->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_x'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'simulation_requires_api_key');
});

test('sandbox simulation settlement proxies to mock bank settle-now', function (): void {
    config(['services.mock_bank.base_url' => 'http://mock-bank.test']);

    Http::fake([
        'http://mock-bank.test/api/control/settle-now' => Http::response(['ok' => true, 'ref' => 'trf_sim_1'], 200),
    ]);

    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_sim',
        ]);

    Payment::factory()
        ->processing()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'metadata' => ['bank_transfer_id' => 'trf_sim_1'],
        ]);

    $plain = simulationTestApiKey($owner, 'sandbox', ['wallet:read', 'wallet:pay', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_sim_1'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('resource', 'payment');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'http://mock-bank.test/api/control/settle-now'
            && $request['ref'] === 'trf_sim_1';
    });
});

test('sandbox simulation microdeposit returns expected cents', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = simulationTestApiKey($owner, 'sandbox', ['wallet:read', 'wallet:link', 'sandbox:simulate']);

    $link = BankLink::factory()
        ->microdepositSent()
        ->create([
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'metadata' => [
                'microdeposit_expected_cents' => [12, 34],
            ],
        ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/microdeposit', ['bank_link_id' => $link->public_id])
        ->assertOk()
        ->assertJsonPath('amounts_cents', [12, 34]);
});

test('sandbox simulation kyc approve activates wallet', function (): void {
    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'secret',
    ]);

    Http::fake([
        'http://mock-bank.test/api/accounts' => Http::response([
            'id' => 'acct_sim_kyc',
            'currency' => 'USD',
            'created_at' => '2026-01-01T00:00:00.000Z',
        ], 201),
    ]);

    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'status' => 'pending',
            'partner_account_id' => null,
        ]);

    $kyc = WalletKycVerification::query()->create([
        'wallet_account_id' => $wallet->id,
        'status' => WalletKycVerificationPending::class,
        'mock_kyc_submission_id' => 'kyc_sim',
        'submitted_payload' => [],
    ]);

    $plain = simulationTestApiKey($owner, 'sandbox', ['wallet:read', 'wallet:pay', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/kyc-approve', [
            'wallet_kyc_verification_id' => $kyc->getKey(),
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonMissingPath('partner_account_id');

    expect($kyc->fresh()->status->getValue())->toBe('approved');
});

test('sandbox simulation return calls mock bank ach-return', function (): void {
    config(['services.mock_bank.base_url' => 'http://mock-bank.test']);

    Http::fake([
        'http://mock-bank.test/api/control/ach-return' => Http::response(['ok' => true, 'ref' => 'trf_ret_api'], 200),
    ]);

    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
        ]);

    Payment::factory()
        ->settled()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'metadata' => [
                'bank_transfer_id' => 'trf_ret_api',
                'settlement_ledger_entry_id' => 999,
            ],
        ]);

    $plain = simulationTestApiKey($owner, 'sandbox', ['wallet:read', 'wallet:pay', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/return', ['bank_transfer_id' => 'trf_ret_api'])
        ->assertOk()
        ->assertJsonPath('ok', true);

    Http::assertSent(function ($request): bool {
        return $request->url() === 'http://mock-bank.test/api/control/ach-return'
            && $request['ref'] === 'trf_ret_api';
    });
});

test('sandbox simulation rejects missing sandbox simulate ability', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = simulationTestApiKey($owner, 'sandbox', ['wallet:read', 'wallet:pay']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/microdeposit', ['bank_link_id' => 'bl_missing'])
        ->assertStatus(403);
});

test('sandbox simulation settlement resolves topup processing', function (): void {
    config(['services.mock_bank.base_url' => 'http://mock-bank.test']);

    Http::fake([
        'http://mock-bank.test/api/control/settle-now' => Http::response(['ok' => true, 'ref' => 'trf_top_sim'], 200),
    ]);

    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_top',
        ]);

    Topup::factory()
        ->processing()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'metadata' => ['bank_transfer_id' => 'trf_top_sim'],
        ]);

    $plain = simulationTestApiKey($owner, 'sandbox', ['wallet:read', 'wallet:topup', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_top_sim'])
        ->assertOk()
        ->assertJsonPath('resource', 'topup');
});
