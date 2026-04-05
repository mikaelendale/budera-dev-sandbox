<?php

use App\Models\Company;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookOutbox;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

test('guest is redirected from company webhooks', function (): void {
    $this->get(route('company.webhooks.index'))->assertRedirect();
});

test('company owner can create webhook endpoint and receives one-time signing secret in session', function (): void {
    $user = User::factory()->withCompany()->create();
    actingAs($user);

    $response = $this->from(route('company.webhooks.index'))->post(route('company.webhooks.store'), [
        'url' => 'https://example.test/hooks',
        'events' => ['account.active', 'test.ping'],
        'environment' => 'sandbox',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('company.webhooks.index'));
    $response->assertSessionHas('one_time_webhook_signing_secret');

    expect(WebhookEndpoint::query()->count())->toBe(1);
    expect(WebhookEndpoint::query()->first()?->url)->toBe('https://example.test/hooks');
});

test('member without company.webhooks.manage cannot view webhooks index', function (): void {
    $owner = User::factory()->withCompany()->create();
    $company = $owner->firstCompany();
    expect($company)->not->toBeNull();

    $limited = User::factory()->create();
    $teamKey = config('permission.column_names.team_foreign_key');
    foreach (['company.keys.view', 'company.sandbox.use'] as $perm) {
        Permission::findOrCreate($perm, 'web');
    }
    setPermissionsTeamId($company->getKey());
    $role = Role::query()->create([
        'name' => 'limited_webhook_tester',
        'guard_name' => 'web',
        $teamKey => $company->getKey(),
    ]);
    $role->syncPermissions(['company.keys.view', 'company.sandbox.use']);
    $limited->assignRole($role);
    setPermissionsTeamId(null);

    actingAs($limited);

    $this->get(route('company.webhooks.index'))->assertForbidden();
});

test('test ping posts signed payload and marks delivery delivered', function (): void {
    Http::fake([
        'https://receiver.test/*' => Http::response('ok', 200),
    ]);

    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    actingAs($user);

    $endpoint = WebhookEndpoint::factory()->create([
        'company_id' => $company->getKey(),
        'url' => 'https://receiver.test/inbound',
        'secret' => 'signing-secret-test',
        'events' => ['*'],
        'environment' => 'sandbox',
        'is_active' => true,
    ]);

    $this->from(route('company.webhooks.index'))
        ->post(route('company.webhooks.test', $endpoint))
        ->assertRedirect(route('company.webhooks.index'));

    $delivery = WebhookDelivery::query()->where('webhook_endpoint_id', $endpoint->getKey())->first();
    expect($delivery)->not->toBeNull();
    expect($delivery->status)->toBe('delivered');

    Http::assertSent(function ($request) {
        $body = $request->body();
        $sigHeader = $request->header('X-Budera-Signature');
        $sig = is_array($sigHeader) ? ($sigHeader[0] ?? '') : (string) $sigHeader;
        $expected = hash_hmac('sha256', $body, 'signing-secret-test');

        return $request->url() === 'https://receiver.test/inbound'
            && $sig === $expected;
    });
});

test('webhooks dispatch command processes queued delivery', function (): void {
    Http::fake([
        'https://dispatch.test/*' => Http::response('', 204),
    ]);

    $company = Company::factory()->create();
    $endpoint = WebhookEndpoint::factory()->create([
        'company_id' => $company->getKey(),
        'url' => 'https://dispatch.test/hook',
        'secret' => 'dispatch-secret',
        'events' => ['payment.settled'],
        'environment' => 'sandbox',
        'is_active' => true,
    ]);

    $delivery = WebhookDelivery::query()->create([
        'webhook_outbox_id' => null,
        'webhook_endpoint_id' => $endpoint->getKey(),
        'event' => 'payment.settled',
        'event_id' => 'evt_test',
        'payload' => [
            'event' => 'payment.settled',
            'event_id' => 'evt_test',
            'created_at' => now()->toIso8601String(),
            'environment' => 'sandbox',
            'data' => [],
        ],
        'status' => 'queued',
    ]);

    $this->artisan('webhooks:dispatch');

    $delivery->refresh();
    expect($delivery->status)->toBe('delivered');
});

test('enqueue webhook fans out to subscribed endpoint', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();

    WebhookEndpoint::factory()->create([
        'company_id' => $company->getKey(),
        'url' => 'https://fanout.test/h',
        'secret' => 's',
        'events' => ['account.active'],
        'environment' => 'sandbox',
        'is_active' => true,
    ]);

    app(AuditService::class)->enqueueWebhook(
        'account.active',
        [
            'event' => 'account.active',
            'data' => [
                'wallet_account_id' => 'wa_1',
                'company_id' => (string) $company->getKey(),
            ],
        ],
        [
            'environment' => 'sandbox',
            'company_id' => $company->getKey(),
        ],
    );

    expect(WebhookOutbox::query()->count())->toBe(1);
    expect(WebhookDelivery::query()->count())->toBe(1);
    expect(WebhookDelivery::query()->first()?->event)->toBe('account.active');
});
