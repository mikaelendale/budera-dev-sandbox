<?php

use App\Services\Banking\MockBankClient;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'test-secret',
    ]);
});

test('health requests base url and sends bank secret', function (): void {
    Http::fake([
        'http://mock-bank.test/health' => Http::response(['ok' => true, 'service' => 'column-mock'], 200),
    ]);

    $client = MockBankClient::fromConfig();
    $data = $client->health();

    expect($data['ok'])->toBeTrue()
        ->and($data['service'])->toBe('column-mock');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'http://mock-bank.test/health'
            && $request->header('X-Bank-Secret')[0] === 'test-secret';
    });
});

test('create account posts json', function (): void {
    Http::fake([
        'http://mock-bank.test/api/accounts' => Http::response([
            'id' => 'acct_1',
            'currency' => 'USD',
            'created_at' => '2026-01-01T00:00:00.000Z',
        ], 201),
    ]);

    $client = MockBankClient::fromConfig();
    $data = $client->createAccount('USD');

    expect($data['id'])->toBe('acct_1');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'http://mock-bank.test/api/accounts'
            && $request['currency'] === 'USD';
    });
});

test('inline mode returns synthetic kyc and account without http', function (): void {
    Http::fake();

    config([
        'services.mock_bank.base_url' => 'http://mock-bank.test',
        'services.mock_bank.secret' => 'test-secret',
        'services.mock_bank.inline' => true,
    ]);

    $client = MockBankClient::fromConfig();

    $kyc = $client->submitKycSubmission(['legal_name' => 'Test']);
    expect($kyc)->toHaveKey('id')
        ->and($kyc['id'])->toStartWith('kyc_')
        ->and($kyc['status'] ?? null)->toBe('pending');

    $acct = $client->createAccount('USD');
    expect($acct)->toHaveKey('id')
        ->and($acct['id'])->toStartWith('acct_')
        ->and($acct['currency'] ?? null)->toBe('USD');

    Http::assertNothingSent();
});

test('transfer ach posts to unified endpoint', function (): void {
    Http::fake([
        'http://mock-bank.test/api/transfers/ach' => Http::response([
            'transfer_id' => 'trf_1',
            'ref' => 'trf_1',
            'rail' => 'ach',
            'status' => 'pending',
            'duplicate' => false,
        ], 202),
    ]);

    $client = MockBankClient::fromConfig();
    $data = $client->achPush('acct_1', 100);

    expect($data['transfer_id'])->toBe('trf_1');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'http://mock-bank.test/api/transfers/ach'
            && $request['direction'] === 'credit'
            && $request['account_id'] === 'acct_1'
            && $request['amount_cents'] === 100;
    });
});
