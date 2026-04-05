<?php

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('company owner can view api keys page', function () {
    $user = User::factory()->withCompany('Acme')->create();

    $this->actingAs($user)
        ->get(route('company.api-keys.index'))
        ->assertOk();
});

test('company developer can view api keys page but not create keys', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $developer = User::factory()->create();
    assignTeamRole($developer, 'company_developer', $company);

    $this->actingAs($developer)
        ->get(route('company.api-keys.index'))
        ->assertOk();

    $this->actingAs($developer)
        ->post(route('company.api-keys.store'), [
            'environment' => 'sandbox',
            'abilities' => ['wallet:read'],
        ])
        ->assertForbidden();
});

test('company owner can create sandbox api key and receives plain key once', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $this->actingAs($owner)
        ->post(route('company.api-keys.store'), [
            'environment' => 'sandbox',
            'abilities' => ['wallet:read', 'wallet:pay'],
        ])
        ->assertRedirect(route('company.api-keys.index'))
        ->assertSessionHas('one_time_plain_text_key');

    $apiKey = ApiKey::query()->where('company_id', $company->id)->latest()->first();

    expect($apiKey)->not()->toBeNull();
    expect((string) $apiKey->status)->toBe('active');
    expect($apiKey->environment)->toBe('sandbox');
    expect($apiKey->key_hash)->not()->toBeNull();
});

test('live api key creation is blocked until company live is enabled', function () {
    $owner = User::factory()->withCompany('Acme')->create();

    $this->actingAs($owner)
        ->from(route('company.api-keys.index'))
        ->post(route('company.api-keys.store'), [
            'environment' => 'live',
            'abilities' => ['wallet:read'],
        ])
        ->assertRedirect(route('company.api-keys.index'))
        ->assertSessionHasErrors('environment');
});

test('live api key creation succeeds when company has live_enabled_at', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $company->live_enabled_at = now();
    $company->save();

    $this->actingAs($owner)
        ->from(route('company.api-keys.index'))
        ->post(route('company.api-keys.store'), [
            'environment' => 'live',
            'abilities' => ['wallet:read', 'wallet:pay'],
        ])
        ->assertRedirect(route('company.api-keys.index'))
        ->assertSessionHas('one_time_plain_text_key');

    $apiKey = ApiKey::query()->where('company_id', $company->id)->latest()->first();

    expect($apiKey)->not()->toBeNull();
    expect($apiKey->environment)->toBe('live');
    expect((string) $apiKey->status)->toBe('active');
});

test('company owner can rotate and revoke api keys', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $seedPlain = 'sk_sandbox_'.Str::random(42);
    $apiKey = ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $seedPlain),
        'abilities' => ['wallet:read'],
        'metadata' => ['key_last4' => substr($seedPlain, -4)],
    ]);

    $this->actingAs($owner)
        ->post(route('company.api-keys.rotate', $apiKey))
        ->assertRedirect(route('company.api-keys.index'))
        ->assertSessionHas('one_time_plain_text_key');

    $apiKey->refresh();
    expect((string) $apiKey->status)->toBe('rotated');
    expect($apiKey->revoked_at)->not()->toBeNull();

    $newKey = ApiKey::query()
        ->where('company_id', $company->id)
        ->where('id', '!=', $apiKey->id)
        ->latest()
        ->first();

    expect($newKey)->not()->toBeNull();
    expect((string) $newKey->status)->toBe('active');

    $this->actingAs($owner)
        ->delete(route('company.api-keys.revoke', $newKey))
        ->assertRedirect();

    $newKey->refresh();
    expect((string) $newKey->status)->toBe('revoked');
    expect($newKey->revoked_at)->not()->toBeNull();
});
