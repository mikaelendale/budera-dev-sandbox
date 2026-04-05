<?php

/** @noinspection PhpUndefinedMethodInspection */

use App\Contracts\Banking\ColumnBankService;
use App\Models\PartnerBankIntegration;
use App\Models\User;
use App\Services\Banking\ColumnBankMock;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;

test('budera admin can view partner banks page', function () {
    $admin = User::factory()->buderaAdmin()->create();

    /** @var TestCase $t */
    $t = $this;

    $t->actingAs($admin)
        ->get(route('admin.partner-banks.index'))
        ->assertOk();
});

test('non-admin cannot view partner banks page', function () {
    $user = User::factory()->withCompany('Acme')->create();

    /** @var TestCase $t */
    $t = $this;

    $t->actingAs($user)
        ->get(route('admin.partner-banks.index'))
        ->assertForbidden();
});

test('budera admin can create partner bank integration', function () {
    $admin = User::factory()->buderaAdmin()->create();

    /** @var TestCase $t */
    $t = $this;

    $t->actingAs($admin)
        ->post(route('admin.partner-banks.store'), [
            'label' => 'Mock sandbox',
            'provider' => 'mock_bank',
            'environment' => 'sandbox',
            'base_url' => 'http://localhost:3000',
            'outbound_api_secret' => 'sk_test',
            'inbound_webhook_secret' => 'whsec_test',
        ])
        ->assertRedirect();

    $row = PartnerBankIntegration::query()->firstOrFail();
    expect($row->provider)->toBe('mock_bank')
        ->and($row->label)->toBe('Mock sandbox');

    $creds = $row->credentials;
    expect($creds['outbound_api_secret'])->toBe('sk_test')
        ->and($creds['inbound_webhook_secret'])->toBe('whsec_test');
});

test('budera admin can update secrets without clearing when omitted', function () {
    $admin = User::factory()->buderaAdmin()->create();

    $i = PartnerBankIntegration::query()->create([
        'label' => 'A',
        'provider' => 'mock_bank',
        'environment' => 'sandbox',
        'base_url' => null,
        'credentials' => [
            'outbound_api_secret' => 'keep',
            'inbound_webhook_secret' => 'also',
        ],
        'is_active' => true,
    ]);

    /** @var TestCase $t */
    $t = $this;

    $t->actingAs($admin)
        ->patch(route('admin.partner-banks.update', $i), [
            'label' => 'B',
            'environment' => 'sandbox',
            'base_url' => '',
            'outbound_api_secret' => '',
            'inbound_webhook_secret' => '',
            'is_active' => false,
        ])
        ->assertRedirect();

    $i->refresh();
    expect($i->label)->toBe('B')
        ->and($i->is_active)->toBeFalse();

    $c = $i->credentials;
    expect($c['outbound_api_secret'])->toBe('keep')
        ->and($c['inbound_webhook_secret'])->toBe('also');
});

test('column bank service resolves to mock in tests', function () {
    expect(app(ColumnBankService::class))->toBeInstanceOf(ColumnBankMock::class);
});

test('budera admin can test mock bank integration health', function (): void {
    Http::fake([
        'http://mock-bank.test/health' => Http::response([
            'ok' => true,
            'service' => 'column-mock',
        ], 200),
    ]);

    $admin = User::factory()->buderaAdmin()->create();
    $i = PartnerBankIntegration::query()->create([
        'label' => 'Mock bank sandbox',
        'provider' => 'mock_bank',
        'environment' => 'sandbox',
        'base_url' => 'http://mock-bank.test',
        'credentials' => [
            'outbound_api_secret' => 'secret',
            'inbound_webhook_secret' => 'whsec_test',
        ],
        'is_active' => true,
    ]);

    /** @var TestCase $t */
    $t = $this;

    $t->actingAs($admin)
        ->post(route('admin.partner-banks.test', $i))
        ->assertRedirect(route('admin.partner-banks.index'))
        ->assertSessionHas('status');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'http://mock-bank.test/health'
            && $request->header('X-Bank-Secret')[0] === 'secret';
    });
});
