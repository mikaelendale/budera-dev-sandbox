<?php

use App\Jobs\DispatchWebhookOutboxJob;
use App\Jobs\ProcessWebhookDeliveryJob;
use App\Models\Company;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookOutbox;
use App\Services\Webhooks\WebhookOutboxPayloadFactory;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
//  ProcessWebhookDeliveryJob
// ---------------------------------------------------------------------------

test('process webhook delivery job delivers successfully with HMAC signature', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);

    $company = Company::factory()->create();
    $endpoint = WebhookEndpoint::factory()->create([
        'company_id' => $company->getKey(),
        'url' => 'https://receiver.test/hook',
        'secret' => 'test-signing-secret',
        'events' => ['payment.settled'],
        'environment' => 'sandbox',
        'is_active' => true,
    ]);

    $delivery = WebhookDelivery::query()->create([
        'webhook_endpoint_id' => $endpoint->getKey(),
        'event' => 'payment.settled',
        'event_id' => 'evt_001',
        'payload' => [
            'event' => 'payment.settled',
            'event_id' => 'evt_001',
            'created_at' => now()->toIso8601String(),
            'data' => ['amount' => 500],
        ],
        'status' => 'queued',
    ]);

    (new ProcessWebhookDeliveryJob($delivery->getKey()))->handle();

    $delivery->refresh();

    expect($delivery->status)->toBe('delivered')
        ->and($delivery->response_status)->toBe(200)
        ->and($delivery->attempts)->toBe(1);
});

test('process webhook delivery job marks failed when endpoint is inactive', function (): void {
    $company = Company::factory()->create();
    $endpoint = WebhookEndpoint::factory()->inactive()->create([
        'company_id' => $company->getKey(),
    ]);

    $delivery = WebhookDelivery::query()->create([
        'webhook_endpoint_id' => $endpoint->getKey(),
        'event' => 'payment.settled',
        'event_id' => 'evt_002',
        'payload' => ['event' => 'payment.settled', 'data' => []],
        'status' => 'queued',
    ]);

    (new ProcessWebhookDeliveryJob($delivery->getKey()))->handle();

    $delivery->refresh();

    expect($delivery->status)->toBe('failed')
        ->and($delivery->response_body)->toContain('inactive');
});

test('process webhook delivery job returns early when delivery is missing', function (): void {
    (new ProcessWebhookDeliveryJob(999999))->handle();

    expect(WebhookDelivery::query()->count())->toBe(0);
});

test('process webhook delivery job skips already delivered delivery', function (): void {
    $company = Company::factory()->create();
    $endpoint = WebhookEndpoint::factory()->create([
        'company_id' => $company->getKey(),
    ]);

    $delivery = WebhookDelivery::query()->create([
        'webhook_endpoint_id' => $endpoint->getKey(),
        'event' => 'payment.settled',
        'event_id' => 'evt_003',
        'payload' => ['event' => 'payment.settled', 'data' => []],
        'status' => 'delivered',
    ]);

    (new ProcessWebhookDeliveryJob($delivery->getKey()))->handle();

    $delivery->refresh();

    expect($delivery->status)->toBe('delivered')
        ->and($delivery->attempts)->toBe(0);
});

test('process webhook delivery job retries on non-2xx response and fails after max attempts', function (): void {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $company = Company::factory()->create();
    $endpoint = WebhookEndpoint::factory()->create([
        'company_id' => $company->getKey(),
        'url' => 'https://failing.test/hook',
        'secret' => 'retry-secret',
        'events' => ['payment.settled'],
        'environment' => 'sandbox',
        'is_active' => true,
    ]);

    $delivery = WebhookDelivery::query()->create([
        'webhook_endpoint_id' => $endpoint->getKey(),
        'event' => 'payment.settled',
        'event_id' => 'evt_004',
        'payload' => ['event' => 'payment.settled', 'data' => []],
        'status' => 'queued',
    ]);

    try {
        (new ProcessWebhookDeliveryJob($delivery->getKey()))->handle();
    } catch (RuntimeException) {
    }

    $delivery->refresh();

    expect($delivery->attempts)->toBe(1)
        ->and($delivery->status)->toBe('queued')
        ->and($delivery->response_status)->toBe(500);

    $delivery->forceFill(['attempts' => 4, 'status' => 'queued'])->save();

    (new ProcessWebhookDeliveryJob($delivery->getKey()))->handle();

    $delivery->refresh();

    expect($delivery->attempts)->toBe(5)
        ->and($delivery->status)->toBe('failed');
});

test('process webhook delivery job sends correct HMAC signature header', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);

    $secret = 'hmac-verification-secret';

    $company = Company::factory()->create();
    $endpoint = WebhookEndpoint::factory()->create([
        'company_id' => $company->getKey(),
        'url' => 'https://hmac.test/hook',
        'secret' => $secret,
        'events' => ['payment.settled'],
        'environment' => 'sandbox',
        'is_active' => true,
    ]);

    $payload = [
        'event' => 'payment.settled',
        'event_id' => 'evt_005',
        'created_at' => now()->toIso8601String(),
        'data' => ['id' => 'pay_abc'],
    ];

    $delivery = WebhookDelivery::query()->create([
        'webhook_endpoint_id' => $endpoint->getKey(),
        'event' => 'payment.settled',
        'event_id' => 'evt_005',
        'payload' => $payload,
        'status' => 'queued',
    ]);

    (new ProcessWebhookDeliveryJob($delivery->getKey()))->handle();

    Http::assertSent(function ($request) use ($secret) {
        $body = $request->body();
        $sigHeader = $request->header('X-Budera-Signature');
        $signature = is_array($sigHeader) ? ($sigHeader[0] ?? '') : (string) $sigHeader;
        $expected = hash_hmac('sha256', $body, $secret);

        return $request->url() === 'https://hmac.test/hook'
            && $signature === $expected;
    });
});

// ---------------------------------------------------------------------------
//  DispatchWebhookOutboxJob
// ---------------------------------------------------------------------------

test('dispatch webhook outbox job marks outbox dispatched on success', function (): void {
    $mock = new MockHandler([new GuzzleResponse(200, [], 'ok')]);
    app()->bind(GuzzleClient::class, fn () => new GuzzleClient(['handler' => HandlerStack::create($mock)]));

    $company = Company::factory()->create();
    $outbox = WebhookOutbox::factory()->create([
        'company_id' => $company->getKey(),
        'event' => 'payment.settled',
        'payload' => [
            'event' => 'payment.settled',
            'event_id' => 'evt_outbox_1',
            'data' => ['amount' => 1000],
        ],
        'destination_url' => 'https://outbox.test/hook',
        'destination_key' => 'outbox-secret',
        'status' => 'queued',
        'environment' => 'sandbox',
    ]);

    app()->call(
        [new DispatchWebhookOutboxJob($outbox->getKey()), 'handle'],
    );

    $outbox->refresh();

    expect($outbox->status)->toBe('dispatched')
        ->and($outbox->reserved_at)->toBeNull();
});

test('dispatch webhook outbox job marks routed when no destination url', function (): void {
    $company = Company::factory()->create();
    $outbox = WebhookOutbox::factory()->create([
        'company_id' => $company->getKey(),
        'destination_url' => null,
        'destination_key' => null,
        'status' => 'queued',
    ]);

    app()->call(
        [new DispatchWebhookOutboxJob($outbox->getKey()), 'handle'],
    );

    $outbox->refresh();

    expect($outbox->status)->toBe('routed')
        ->and($outbox->reserved_at)->toBeNull();
});

test('dispatch webhook outbox job marks failed on exception', function (): void {
    $factory = Mockery::mock(WebhookOutboxPayloadFactory::class);
    $factory->shouldReceive('forEvent')
        ->andThrow(new RuntimeException('Downstream failure'));
    app()->instance(WebhookOutboxPayloadFactory::class, $factory);

    $company = Company::factory()->create();
    $outbox = WebhookOutbox::factory()->create([
        'company_id' => $company->getKey(),
        'event' => 'payment.settled',
        'payload' => [
            'event' => 'payment.settled',
            'event_id' => 'evt_outbox_fail',
            'data' => ['amount' => 200],
        ],
        'destination_url' => 'https://fail.test/hook',
        'destination_key' => 'fail-secret',
        'status' => 'queued',
        'environment' => 'sandbox',
    ]);

    app()->call(
        [new DispatchWebhookOutboxJob($outbox->getKey()), 'handle'],
    );

    $outbox->refresh();

    expect($outbox->status)->toBe('failed')
        ->and($outbox->last_error)->not->toBeNull()
        ->and($outbox->last_error)->toContain('Downstream failure');
});
