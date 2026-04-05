<?php

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('api can send test ping to webhook endpoint with webhooks manage ability', function (): void {
    Http::fake([
        'https://api-receiver.test/*' => Http::response('ok', 200),
    ]);

    $owner = User::factory()->withCompany('Hook Co')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $endpoint = WebhookEndpoint::factory()->create([
        'company_id' => $company->getKey(),
        'url' => 'https://api-receiver.test/inbound',
        'secret' => 'whsec_test_secret',
        'events' => ['*'],
        'environment' => 'sandbox',
        'is_active' => true,
    ]);

    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->getKey(),
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['webhooks:manage'],
        'metadata' => [],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson("/api/v1/company/webhooks/{$endpoint->getKey()}/test")
        ->assertOk()
        ->assertJsonPath('ok', true);

    Http::assertSent(function ($request): bool {
        $sigHeader = $request->header('X-Budera-Signature');
        $sig = is_array($sigHeader) ? ($sigHeader[0] ?? '') : (string) $sigHeader;

        return $request->url() === 'https://api-receiver.test/inbound'
            && $sig !== '';
    });
});

test('api webhook test returns 403 without webhooks manage ability', function (): void {
    $owner = User::factory()->withCompany('Hook Co')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $endpoint = WebhookEndpoint::factory()->create([
        'company_id' => $company->getKey(),
        'url' => 'https://api-receiver.test/inbound',
        'secret' => 'whsec_test_secret',
        'events' => ['*'],
        'environment' => 'sandbox',
        'is_active' => true,
    ]);

    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->getKey(),
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read'],
        'metadata' => [],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson("/api/v1/company/webhooks/{$endpoint->getKey()}/test")
        ->assertForbidden();
});
