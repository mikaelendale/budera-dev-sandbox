<?php

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Topup;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookOutbox;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\MockBankWebhook;

test('full agent money path: api key wallet kyc webhook bank link topup payment webhooks ledger balance reconcile', function (): void {
    $webhookSecret = 'whsec_full_agent_path';
    config(['services.mock_bank.webhook_secret' => $webhookSecret]);
    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'secret',
    ]);

    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, '/api/kyc/submissions')) {
            return Http::response([
                'id' => 'kyc_full_path',
                'status' => 'pending',
                'created_at' => '2026-01-01T00:00:00.000Z',
            ], 201);
        }

        if (str_contains($url, '/api/accounts')) {
            return Http::response([
                'id' => 'acct_full_path',
                'currency' => 'USD',
                'created_at' => '2026-01-01T00:00:00.000Z',
            ], 201);
        }

        if (str_contains($url, '/api/transfers/ach')) {
            /** @var array<string, mixed> $body */
            $body = $request->data();
            $direction = isset($body['direction']) ? (string) $body['direction'] : '';

            if ($direction === 'debit') {
                return Http::response([
                    'transfer_id' => 'trf_e2e_top',
                    'ref' => 'trf_e2e_top',
                    'rail' => 'ach',
                    'status' => 'pending',
                    'duplicate' => false,
                ], 202);
            }

            return Http::response([
                'transfer_id' => 'trf_e2e_pay',
                'ref' => 'trf_e2e_pay',
                'rail' => 'ach',
                'status' => 'pending',
                'duplicate' => false,
            ], 202);
        }

        return Http::response(['error' => 'http_fake_unhandled', 'url' => $url], 500);
    });

    $owner = User::factory()->withCompany('FullPath Co')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read', 'wallet:pay', 'wallet:topup', 'wallet:link'],
        'metadata' => [],
    ]);

    $auth = ['Authorization' => 'Bearer '.$plain];

    $walletRes = $this->withHeaders($auth)->postJson('/api/v1/wallet/accounts');
    $walletRes->assertCreated();
    $walletPublicId = $walletRes->json('id');
    expect(is_string($walletPublicId))->toBeTrue();

    $this->withHeaders($auth)->postJson("/api/v1/wallet/accounts/{$walletPublicId}/kyc", [
        'legal_name' => 'Full Path Agent',
    ])->assertCreated()
        ->assertJsonPath('wallet_account_id', $walletPublicId);

    $kycPayload = [
        'event' => 'kyc.verified',
        'id' => 'evt_kyc_full',
        'occurred_at' => now()->toIso8601String(),
        'data' => [
            'kyc_submission_id' => 'kyc_full_path',
            'account_id' => 'ignored',
        ],
    ];
    $signed = MockBankWebhook::signPayload($kycPayload, $webhookSecret);
    $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIGNATURE' => $signed['signature'],
    ], $signed['raw'])->assertOk();

    $wallet = WalletAccount::query()->where('public_id', $walletPublicId)->firstOrFail();
    expect($wallet->partner_account_id)->toBe('acct_full_path')
        ->and((string) $wallet->status->getValue())->toBe('active');

    $bankLinkRes = $this->withHeaders($auth)->postJson('/api/v1/bank-links', [
        'routing_number' => '123456789',
        'account_number' => '1234567890123',
        'environment' => 'sandbox',
    ]);
    $bankLinkRes->assertCreated();
    $bankLinkPublicId = $bankLinkRes->json('data.id');
    expect(is_string($bankLinkPublicId))->toBeTrue();

    $this->withHeaders($auth)->postJson("/api/v1/bank-links/{$bankLinkPublicId}/verify", [
        'amount_first_cents' => 12,
        'amount_second_cents' => 34,
    ])->assertOk()
        ->assertJsonPath('data.status', 'verified');

    $topupCents = 50_000;
    $payCents = 15_000;

    $topupRes = $this->withHeaders(array_merge($auth, [
        'Idempotency-Key' => 'idem_e2e_top_'.Str::uuid()->toString(),
    ]))->postJson('/api/v1/topups', [
        'wallet_account_id' => $walletPublicId,
        'bank_link_id' => $bankLinkPublicId,
        'amount_cents' => $topupCents,
    ]);
    $topupRes->assertCreated()
        ->assertJsonPath('data.status', 'processing');

    $topupPublicId = $topupRes->json('data.id');
    expect(is_string($topupPublicId))->toBeTrue();

    $topupSettle = MockBankWebhook::signPayload([
        'event' => 'transfer.ach.settled',
        'data' => [
            'transfer_id' => 'trf_e2e_top',
            'direction' => 'debit',
            'amount_cents' => $topupCents,
            'rail' => 'ach',
        ],
    ], $webhookSecret);
    $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => $topupSettle['signature'],
    ], $topupSettle['raw'])->assertOk();

    $wallet->refresh();
    expect((int) $wallet->balance_cents)->toBe($topupCents);

    $topupRow = Topup::query()->where('public_id', $topupPublicId)->firstOrFail();
    expect($topupRow->status->getValue())->toBe('settled');

    $payRes = $this->withHeaders(array_merge($auth, [
        'Idempotency-Key' => 'idem_e2e_pay_'.Str::uuid()->toString(),
    ]))->postJson('/api/v1/payments', [
        'wallet_account_id' => $walletPublicId,
        'amount_cents' => $payCents,
        'payee_ref' => 'vendor@example.com',
    ]);
    $payRes->assertCreated()
        ->assertJsonPath('data.status', 'processing');

    $paymentPublicId = $payRes->json('data.id');
    expect(is_string($paymentPublicId))->toBeTrue();

    $paySettle = MockBankWebhook::signPayload([
        'event' => 'transfer.ach.settled',
        'data' => [
            'transfer_id' => 'trf_e2e_pay',
            'direction' => 'credit',
            'amount_cents' => $payCents,
            'rail' => 'ach',
        ],
    ], $webhookSecret);
    $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => $paySettle['signature'],
    ], $paySettle['raw'])->assertOk();

    $payment = Payment::query()->where('public_id', $paymentPublicId)->firstOrFail();
    expect($payment->status->getValue())->toBe('settled')
        ->and($payment->metadata['settlement_ledger_entry_id'] ?? null)->not->toBeNull();

    $wallet->refresh();
    $expectedBalance = $topupCents - $payCents;
    expect((int) $wallet->balance_cents)->toBe($expectedBalance);

    expect(Artisan::call('ledger:reconcile'))->toBe(0);
});

test('full agent money path fires outbound webhooks to subscribed company endpoint', function (): void {
    $webhookSecret = 'whsec_full_agent_path';
    config(['services.mock_bank.webhook_secret' => $webhookSecret]);
    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'secret',
    ]);

    $webhookEndpointUrl = 'https://webhooks.test/budera';

    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, 'webhooks.test')) {
            return Http::response(['ok' => true], 200);
        }

        if (str_contains($url, '/api/kyc/submissions')) {
            return Http::response([
                'id' => 'kyc_wh_path',
                'status' => 'pending',
                'created_at' => '2026-01-01T00:00:00.000Z',
            ], 201);
        }

        if (str_contains($url, '/api/accounts')) {
            return Http::response([
                'id' => 'acct_wh_path',
                'currency' => 'USD',
                'created_at' => '2026-01-01T00:00:00.000Z',
            ], 201);
        }

        if (str_contains($url, '/api/transfers/ach')) {
            /** @var array<string, mixed> $body */
            $body = $request->data();
            $direction = isset($body['direction']) ? (string) $body['direction'] : '';

            if ($direction === 'debit') {
                return Http::response([
                    'transfer_id' => 'trf_wh_top',
                    'ref' => 'trf_wh_top',
                    'rail' => 'ach',
                    'status' => 'pending',
                    'duplicate' => false,
                ], 202);
            }

            return Http::response([
                'transfer_id' => 'trf_wh_pay',
                'ref' => 'trf_wh_pay',
                'rail' => 'ach',
                'status' => 'pending',
                'duplicate' => false,
            ], 202);
        }

        return Http::response(['error' => 'http_fake_unhandled', 'url' => $url], 500);
    });

    $owner = User::factory()->withCompany('WebhookPath Co')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read', 'wallet:pay', 'wallet:topup', 'wallet:link'],
        'metadata' => [],
    ]);

    $endpoint = WebhookEndpoint::query()->withoutCompanyScope()->create([
        'company_id' => $company->id,
        'url' => $webhookEndpointUrl,
        'secret' => Str::random(32),
        'events' => ['*'],
        'environment' => 'sandbox',
        'is_active' => true,
    ]);

    $auth = ['Authorization' => 'Bearer '.$plain];

    expect(WebhookOutbox::query()->count())->toBe(0);
    expect(WebhookDelivery::query()->count())->toBe(0);

    // ── Wallet creation ──
    $walletRes = $this->withHeaders($auth)->postJson('/api/v1/wallet/accounts');
    $walletRes->assertCreated();
    $walletPublicId = $walletRes->json('id');
    expect(is_string($walletPublicId))->toBeTrue();

    // ── KYC submission ──
    $this->withHeaders($auth)->postJson("/api/v1/wallet/accounts/{$walletPublicId}/kyc", [
        'legal_name' => 'Webhook Path Agent',
    ])->assertCreated()
        ->assertJsonPath('wallet_account_id', $walletPublicId);

    $outboxBeforeKyc = WebhookOutbox::query()->count();

    // ── KYC approved webhook → triggers kyc.approved + account.active outbound webhooks ──
    $kycPayload = [
        'event' => 'kyc.verified',
        'id' => 'evt_kyc_wh',
        'occurred_at' => now()->toIso8601String(),
        'data' => [
            'kyc_submission_id' => 'kyc_wh_path',
            'account_id' => 'ignored',
        ],
    ];
    $signed = MockBankWebhook::signPayload($kycPayload, $webhookSecret);
    $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIGNATURE' => $signed['signature'],
    ], $signed['raw'])->assertOk();

    $wallet = WalletAccount::query()->where('public_id', $walletPublicId)->firstOrFail();
    expect((string) $wallet->status->getValue())->toBe('active');

    // ── Assert outbox entries for kyc.approved and account.active ──
    $outboxAfterKyc = WebhookOutbox::query()->count();
    expect($outboxAfterKyc - $outboxBeforeKyc)->toBeGreaterThanOrEqual(2);

    $kycApprovedOutbox = WebhookOutbox::query()
        ->where('event', 'kyc.approved')
        ->where('company_id', $company->id)
        ->first();
    expect($kycApprovedOutbox)->not->toBeNull()
        ->and($kycApprovedOutbox->environment)->toBe('sandbox');

    $accountActiveOutbox = WebhookOutbox::query()
        ->where('event', 'account.active')
        ->where('company_id', $company->id)
        ->first();
    expect($accountActiveOutbox)->not->toBeNull()
        ->and($accountActiveOutbox->environment)->toBe('sandbox');

    // ── Assert fan-out created WebhookDelivery for each outbox entry ──
    $kycDelivery = WebhookDelivery::query()
        ->where('event', 'kyc.approved')
        ->where('webhook_outbox_id', $kycApprovedOutbox->getKey())
        ->first();
    expect($kycDelivery)->not->toBeNull()
        ->and((int) $kycDelivery->webhook_endpoint_id)->toBe((int) $endpoint->getKey())
        ->and($kycDelivery->status)->toBe('queued')
        ->and($kycDelivery->payload)->toBeArray()
        ->and($kycDelivery->payload['event'])->toBe('kyc.approved')
        ->and($kycDelivery->payload['data']['wallet_account_id'])->toBe((string) $wallet->public_id);

    $accountDelivery = WebhookDelivery::query()
        ->where('event', 'account.active')
        ->where('webhook_outbox_id', $accountActiveOutbox->getKey())
        ->first();
    expect($accountDelivery)->not->toBeNull()
        ->and((int) $accountDelivery->webhook_endpoint_id)->toBe((int) $endpoint->getKey())
        ->and($accountDelivery->status)->toBe('queued')
        ->and($accountDelivery->payload)->toBeArray()
        ->and($accountDelivery->payload['event'])->toBe('account.active')
        ->and($accountDelivery->payload['data']['wallet_account_id'])->toBe((string) $wallet->public_id);

    // ── Bank link ──
    $bankLinkRes = $this->withHeaders($auth)->postJson('/api/v1/bank-links', [
        'routing_number' => '123456789',
        'account_number' => '1234567890123',
        'environment' => 'sandbox',
    ]);
    $bankLinkRes->assertCreated();
    $bankLinkPublicId = $bankLinkRes->json('data.id');

    $this->withHeaders($auth)->postJson("/api/v1/bank-links/{$bankLinkPublicId}/verify", [
        'amount_first_cents' => 12,
        'amount_second_cents' => 34,
    ])->assertOk();

    // ── Topup + settlement ──
    $topupCents = 50_000;
    $payCents = 15_000;

    $this->withHeaders(array_merge($auth, [
        'Idempotency-Key' => 'idem_wh_top_'.Str::uuid()->toString(),
    ]))->postJson('/api/v1/topups', [
        'wallet_account_id' => $walletPublicId,
        'bank_link_id' => $bankLinkPublicId,
        'amount_cents' => $topupCents,
    ])->assertCreated();

    $topupSettle = MockBankWebhook::signPayload([
        'event' => 'transfer.ach.settled',
        'data' => [
            'transfer_id' => 'trf_wh_top',
            'direction' => 'debit',
            'amount_cents' => $topupCents,
            'rail' => 'ach',
        ],
    ], $webhookSecret);
    $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => $topupSettle['signature'],
    ], $topupSettle['raw'])->assertOk();

    $wallet->refresh();
    expect((int) $wallet->balance_cents)->toBe($topupCents);

    // ── Payment + settlement ──
    $this->withHeaders(array_merge($auth, [
        'Idempotency-Key' => 'idem_wh_pay_'.Str::uuid()->toString(),
    ]))->postJson('/api/v1/payments', [
        'wallet_account_id' => $walletPublicId,
        'amount_cents' => $payCents,
        'payee_ref' => 'vendor@example.com',
    ])->assertCreated();

    $paySettle = MockBankWebhook::signPayload([
        'event' => 'transfer.ach.settled',
        'data' => [
            'transfer_id' => 'trf_wh_pay',
            'direction' => 'credit',
            'amount_cents' => $payCents,
            'rail' => 'ach',
        ],
    ], $webhookSecret);
    $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => $paySettle['signature'],
    ], $paySettle['raw'])->assertOk();

    $wallet->refresh();
    expect((int) $wallet->balance_cents)->toBe($topupCents - $payCents);

    // ── Verify complete outbox state ──
    $allOutbox = WebhookOutbox::query()
        ->where('company_id', $company->id)
        ->get();
    expect($allOutbox->count())->toBeGreaterThanOrEqual(2);
    expect($allOutbox->pluck('event')->toArray())
        ->toContain('kyc.approved')
        ->toContain('account.active');

    // ── Every outbox entry produced exactly one delivery for our endpoint ──
    $allDeliveries = WebhookDelivery::query()
        ->whereIn('webhook_outbox_id', $allOutbox->pluck('id'))
        ->get();
    expect($allDeliveries->count())->toBe($allOutbox->count());

    $allDeliveries->each(function (WebhookDelivery $d) use ($endpoint): void {
        expect((int) $d->webhook_endpoint_id)->toBe((int) $endpoint->getKey())
            ->and($d->status)->toBe('queued')
            ->and($d->payload)->toBeArray()
            ->and($d->payload['event'] ?? null)->toBe($d->event);
    });

    // ── Dispatch queued deliveries via artisan command (sync queue executes inline) ──
    $queuedCount = $allDeliveries->where('status', 'queued')->count();
    expect($queuedCount)->toBeGreaterThanOrEqual(2);

    Artisan::call('webhooks:dispatch');

    $deliveredCount = WebhookDelivery::query()->where('status', 'delivered')->count();
    $remainingQueued = WebhookDelivery::query()->where('status', 'queued')->count();
    expect($deliveredCount)->toBeGreaterThanOrEqual(2)
        ->and($remainingQueued)->toBe(0);

    // ── Verify HTTP POSTs hit the company webhook URL with HMAC signatures ──
    Http::assertSent(function (Request $request) use ($webhookEndpointUrl): bool {
        return $request->url() === $webhookEndpointUrl && $request->hasHeader('X-Budera-Signature');
    });

    // ── All delivered records have 200 response status ──
    WebhookDelivery::query()
        ->where('status', 'delivered')
        ->get()
        ->each(function (WebhookDelivery $d): void {
            expect((int) $d->response_status)->toBe(200);
        });

    // ── No orphaned deliveries pointing to a different endpoint ──
    $orphanedDeliveries = WebhookDelivery::query()
        ->where('webhook_endpoint_id', '!=', $endpoint->getKey())
        ->whereIn('webhook_outbox_id', $allOutbox->pluck('id'))
        ->count();
    expect($orphanedDeliveries)->toBe(0);

    // ── Ledger reconciliation still passes after full flow ──
    expect(Artisan::call('ledger:reconcile'))->toBe(0);
});
