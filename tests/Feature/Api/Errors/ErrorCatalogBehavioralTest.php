<?php

use App\Models\ApiKey;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Topup;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

function catalogApiKey(User $owner, string $environment, array $abilities): string
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

// ---------------------------------------------------------------------------
// Auth layer
// ---------------------------------------------------------------------------

test('unauthenticated_api — no auth header returns 401', function (): void {
    $this->getJson('/api/v1/payments')
        ->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'unauthenticated_api',
                'layer' => 'auth',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('forbidden — creating payment on agent-scoped wallet returns 403', function (): void {
    Http::fake([
        'http://mock-bank.test/api/transfers/ach' => Http::response([
            'transfer_id' => 'trf_fb',
            'ref' => 'trf_fb',
            'rail' => 'ach',
            'status' => 'pending',
            'duplicate' => false,
        ], 202),
    ]);

    config(['services.mock_bank.base_url' => 'http://mock-bank.test']);

    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');
    $plain = catalogApiKey($owner, 'sandbox', ['wallet:pay', 'wallet:read']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_forbidden',
            'agent_id' => 'agent_other',
        ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_forbidden_'.Str::uuid()->toString(),
    ])
        ->postJson('/api/v1/payments', [
            'wallet_account_id' => $wallet->public_id,
            'amount_cents' => 100,
            'payee_ref' => 'test@example.com',
        ])
        ->assertStatus(403)
        ->assertJson([
            'error' => [
                'code' => 'forbidden',
                'layer' => 'auth',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('missing_api_key_ability — key without wallet:pay cannot create payment', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = catalogApiKey($owner, 'sandbox', ['wallet:read']);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'idem_ability_'.Str::uuid()->toString(),
    ])
        ->postJson('/api/v1/payments', [
            'wallet_account_id' => 'wal_nonexistent',
            'amount_cents' => 100,
            'payee_ref' => 'test@example.com',
        ])
        ->assertStatus(403)
        ->assertJson([
            'error' => [
                'code' => 'missing_api_key_ability',
                'layer' => 'auth',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('missing_token_scope — oauth token without required scope returns 403', function (): void {
    $user = User::factory()->withCompany()->create();
    Passport::actingAs($user, ['wallet:read']);

    $this->withHeaders([
        'Idempotency-Key' => 'idem_scope_'.Str::uuid()->toString(),
    ])
        ->postJson('/api/v1/payments', [
            'wallet_account_id' => 'wal_nonexistent',
            'amount_cents' => 100,
            'payee_ref' => 'test@example.com',
        ])
        ->assertStatus(403)
        ->assertJson([
            'error' => [
                'code' => 'missing_token_scope',
                'layer' => 'auth',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('resource_not_found — filtering payments by nonexistent wallet returns 404', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = catalogApiKey($owner, 'sandbox', ['wallet:read']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->getJson('/api/v1/payments?wallet_account_id=wal_nonexistent_xyz')
        ->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'resource_not_found',
                'layer' => 'not_found',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

// ---------------------------------------------------------------------------
// Idempotency layer
// ---------------------------------------------------------------------------

test('IDEMPOTENCY_KEY_REQUIRED — missing header returns 400', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = catalogApiKey($owner, 'sandbox', ['wallet:pay', 'wallet:read']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => (int) Company::query()->where('owner_id', $owner->id)->value('id'),
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_idem_req',
            'balance_cents' => 1_000,
        ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/payments', [
            'wallet_account_id' => $wallet->public_id,
            'amount_cents' => 100,
            'payee_ref' => 'v@example.com',
        ])
        ->assertStatus(400)
        ->assertJson([
            'error' => [
                'code' => 'IDEMPOTENCY_KEY_REQUIRED',
                'layer' => 'idempotency',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('IDEMPOTENCY_KEY_INVALID — overlong key returns 400', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = catalogApiKey($owner, 'sandbox', ['wallet:pay', 'wallet:read']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => (int) Company::query()->where('owner_id', $owner->id)->value('id'),
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_idem_inv',
            'balance_cents' => 1_000,
        ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => str_repeat('x', 256),
    ])
        ->postJson('/api/v1/payments', [
            'wallet_account_id' => $wallet->public_id,
            'amount_cents' => 100,
            'payee_ref' => 'v@example.com',
        ])
        ->assertStatus(400)
        ->assertJson([
            'error' => [
                'code' => 'IDEMPOTENCY_KEY_INVALID',
                'layer' => 'idempotency',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('IDEMPOTENCY_KEY_CONFLICT — same key different body returns 409', function (): void {
    Http::fake([
        'http://mock-bank.test/api/transfers/ach' => Http::response([
            'transfer_id' => 'trf_idem_cf',
            'ref' => 'trf_idem_cf',
            'rail' => 'ach',
            'status' => 'pending',
            'duplicate' => false,
        ], 202),
    ]);

    config(['services.mock_bank.base_url' => 'http://mock-bank.test']);

    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');
    $plain = catalogApiKey($owner, 'sandbox', ['wallet:pay', 'wallet:read']);

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_idem_cf',
            'balance_cents' => 500_000,
        ]);

    app(LedgerService::class)->credit($wallet, 500_000, 'seed', (string) Str::uuid(), 'Test seed');

    $key = 'idem_catalog_cf_'.Str::uuid()->toString();

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/payments', [
        'wallet_account_id' => $wallet->public_id,
        'amount_cents' => 100,
        'payee_ref' => 'a@example.com',
    ])->assertCreated();

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => $key,
    ])->postJson('/api/v1/payments', [
        'wallet_account_id' => $wallet->public_id,
        'amount_cents' => 200,
        'payee_ref' => 'b@example.com',
    ])
        ->assertStatus(409)
        ->assertJson([
            'error' => [
                'code' => 'IDEMPOTENCY_KEY_CONFLICT',
                'layer' => 'idempotency',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

// ---------------------------------------------------------------------------
// Sandbox layer
// ---------------------------------------------------------------------------

test('simulation_forbidden_live_environment — live key hits sandbox route returns 403', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = catalogApiKey($owner, 'live', ['wallet:read', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_live_env'])
        ->assertStatus(403)
        ->assertJson([
            'error' => [
                'code' => 'simulation_forbidden_live_environment',
                'layer' => 'sandbox',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('simulation_requires_api_key — oauth token without api key returns 403', function (): void {
    $user = User::factory()->withCompany()->create();
    Passport::actingAs($user, ['sandbox:simulate', 'wallet:pay']);

    $this->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_oauth'])
        ->assertStatus(403)
        ->assertJson([
            'error' => [
                'code' => 'simulation_requires_api_key',
                'layer' => 'sandbox',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('payment_not_processing — settled payment on settlement simulation returns 422', function (): void {
    config(['services.mock_bank.base_url' => 'http://mock-bank.test']);

    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_pnp',
        ]);

    Payment::factory()
        ->settled()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'metadata' => ['bank_transfer_id' => 'trf_pnp'],
        ]);

    $plain = catalogApiKey($owner, 'sandbox', ['wallet:read', 'wallet:pay', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_pnp'])
        ->assertStatus(422)
        ->assertJson([
            'error' => [
                'code' => 'payment_not_processing',
                'layer' => 'sandbox',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('topup_not_processing — settled topup on settlement simulation returns 422', function (): void {
    config(['services.mock_bank.base_url' => 'http://mock-bank.test']);

    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_tnp',
        ]);

    Topup::factory()
        ->settled()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'metadata' => ['bank_transfer_id' => 'trf_tnp'],
        ]);

    $plain = catalogApiKey($owner, 'sandbox', ['wallet:read', 'wallet:topup', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_tnp'])
        ->assertStatus(422)
        ->assertJson([
            'error' => [
                'code' => 'topup_not_processing',
                'layer' => 'sandbox',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('payment_not_found — return simulation with unknown transfer id returns 404', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = catalogApiKey($owner, 'sandbox', ['wallet:read', 'wallet:pay', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/return', ['bank_transfer_id' => 'trf_ghost'])
        ->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'payment_not_found',
                'layer' => 'not_found',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('payment_not_settled — return simulation on processing payment returns 422', function (): void {
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
        ->processing()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'metadata' => ['bank_transfer_id' => 'trf_pns'],
        ]);

    $plain = catalogApiKey($owner, 'sandbox', ['wallet:read', 'wallet:pay', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/return', ['bank_transfer_id' => 'trf_pns'])
        ->assertStatus(422)
        ->assertJson([
            'error' => [
                'code' => 'payment_not_settled',
                'layer' => 'sandbox',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('payment_missing_settlement_ledger — return simulation without ledger entry returns 422', function (): void {
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
            'metadata' => ['bank_transfer_id' => 'trf_msl'],
        ]);

    $plain = catalogApiKey($owner, 'sandbox', ['wallet:read', 'wallet:pay', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/return', ['bank_transfer_id' => 'trf_msl'])
        ->assertStatus(422)
        ->assertJson([
            'error' => [
                'code' => 'payment_missing_settlement_ledger',
                'layer' => 'sandbox',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('mock_bank_control_failed — mock bank 404 on settlement returns 422', function (): void {
    config(['services.mock_bank.base_url' => 'http://mock-bank.test']);

    Http::fake([
        'http://mock-bank.test/api/control/settle-now' => Http::response('', 404),
    ]);

    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_mbcf',
        ]);

    Payment::factory()
        ->processing()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'metadata' => ['bank_transfer_id' => 'trf_mbcf'],
        ]);

    $plain = catalogApiKey($owner, 'sandbox', ['wallet:read', 'wallet:pay', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_mbcf'])
        ->assertStatus(422)
        ->assertJson([
            'error' => [
                'code' => 'mock_bank_control_failed',
                'layer' => 'sandbox',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('bank_link_not_awaiting_microdeposit — verified link on microdeposit simulation returns 422', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = catalogApiKey($owner, 'sandbox', ['wallet:read', 'wallet:link', 'sandbox:simulate']);

    $link = BankLink::factory()
        ->verified()
        ->create([
            'user_id' => $owner->id,
            'environment' => 'sandbox',
        ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/microdeposit', ['bank_link_id' => $link->public_id])
        ->assertStatus(422)
        ->assertJson([
            'error' => [
                'code' => 'bank_link_not_awaiting_microdeposit',
                'layer' => 'policy',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

// ---------------------------------------------------------------------------
// Policy / validation layer
// ---------------------------------------------------------------------------

test('bank_link_not_awaiting_verification — verified link on verify returns 422', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = catalogApiKey($owner, 'sandbox', ['wallet:link']);

    $link = BankLink::factory()
        ->verified()
        ->create([
            'user_id' => $owner->id,
            'environment' => 'sandbox',
        ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson("/api/v1/bank-links/{$link->public_id}/verify", [
            'amount_first_cents' => 12,
            'amount_second_cents' => 34,
        ])
        ->assertStatus(422)
        ->assertJson([
            'error' => [
                'code' => 'bank_link_not_awaiting_verification',
                'layer' => 'policy',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('bank_link_cannot_revoke — initiated link cannot be deleted returns 422', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = catalogApiKey($owner, 'sandbox', ['wallet:link']);

    $link = BankLink::factory()
        ->create([
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'status' => 'initiated',
        ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->deleteJson("/api/v1/bank-links/{$link->public_id}")
        ->assertStatus(422)
        ->assertJson([
            'error' => [
                'code' => 'bank_link_cannot_revoke',
                'layer' => 'policy',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('microdeposit_verification_failed — wrong amounts on verify returns 422', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = catalogApiKey($owner, 'sandbox', ['wallet:link']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'routing_number' => '123456789',
            'account_number' => '1234567890123',
        ])->assertCreated();

    $link = BankLink::query()->where('user_id', $owner->id)->latest('id')->firstOrFail();

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson("/api/v1/bank-links/{$link->public_id}/verify", [
            'amount_first_cents' => 99,
            'amount_second_cents' => 99,
        ])
        ->assertStatus(422)
        ->assertJson([
            'error' => [
                'code' => 'microdeposit_verification_failed',
                'layer' => 'policy',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('invalid_request — bank link store without credentials or hosted returns 422', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = catalogApiKey($owner, 'sandbox', ['wallet:link']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [])
        ->assertStatus(422)
        ->assertJson([
            'error' => [
                'code' => 'invalid_request',
                'layer' => 'validation',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

test('end_user_not_found — unknown hosted email returns 422', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = catalogApiKey($owner, 'sandbox', ['wallet:link']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'end_user_email' => 'nobody-catalog@example.com',
        ])
        ->assertStatus(422)
        ->assertJson([
            'error' => [
                'code' => 'end_user_not_found',
                'layer' => 'validation',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

// ---------------------------------------------------------------------------
// Rate limiting
// ---------------------------------------------------------------------------

test('rate_limit_exceeded — exceeding per-company limit returns 429', function (): void {
    Cache::flush();
    config(['budera.api_rate_limits.default' => 2]);

    $owner = User::factory()->withCompany()->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    WalletAccount::query()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'partner_account_id' => 'mock_acct_rl_cat',
        'metadata' => [],
    ]);

    $plain = catalogApiKey($owner, 'sandbox', ['wallet:read']);

    $headers = ['Authorization' => 'Bearer '.$plain];

    $this->withHeaders($headers)->getJson('/api/v1/wallet/me')->assertOk();
    $this->withHeaders($headers)->getJson('/api/v1/wallet/me')->assertOk();

    $this->withHeaders($headers)->getJson('/api/v1/wallet/me')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJson([
            'error' => [
                'code' => 'rate_limit_exceeded',
                'layer' => 'policy',
            ],
        ])
        ->assertJsonPath('error.message', fn (string $v): bool => $v !== '');
});

// ---------------------------------------------------------------------------
// Codes intentionally skipped (not reliably triggerable via HTTP)
// ---------------------------------------------------------------------------
// - server_error (500, internal): Requires an unhandled exception; intentionally not triggered in tests.
// - webhook_not_configured (503, webhook): Returned by inbound webhook processing infra, not a standard API route.
// - invalid_signature (401, webhook): Requires a signed webhook delivery from partner bank, not an API consumer route.
// - sandbox_only (422, sandbox): Returned internally by services, not directly by a middleware/controller HTTP path.
// - company_context_required (403, auth): Requires authenticated request with no resolved company — difficult to
//   trigger in isolation since API key auth always resolves a company.
// - company_required (403, auth): Similar to above — web-only middleware guard.
// - end_user_not_in_company (403, auth): Requires an end-user that exists but belongs to a different company — the
//   bank link hosted flow resolves this, but the endpoint is internal.
// - approval_action_failed (422, policy): Requires a specific approval state machine transition failure.
// - approval_action_forbidden (403, auth): Requires acting on another company's approval — the route binding + scope
//   makes this hard to trigger without deep setup.
