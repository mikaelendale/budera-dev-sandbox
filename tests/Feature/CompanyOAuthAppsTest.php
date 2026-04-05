<?php

use App\Models\Company;
use App\Models\User;

test('company owner can view oauth apps page', function () {
    $user = User::factory()->withCompany('Acme')->create();

    $this->actingAs($user)
        ->get(route('company.oauth-apps.index'))
        ->assertOk();
});

test('company developer cannot view oauth apps page', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $developer = User::factory()->create();
    assignTeamRole($developer, 'company_developer', $company);

    $this->actingAs($developer)
        ->get(route('company.oauth-apps.index'))
        ->assertForbidden();
});

test('confidential oauth client creation flashes client secret once', function () {
    $user = User::factory()->withCompany('Acme')->create();

    $this->actingAs($user)
        ->post(route('company.oauth-apps.store'), [
            'name' => 'Confidential app',
            'redirect_uri' => 'https://example.com/callback',
            'is_public' => false,
        ])
        ->assertSessionHas('oauth_client_credentials.client_id')
        ->assertSessionHas('oauth_client_credentials.client_secret');

    $secret = session('oauth_client_credentials.client_secret');
    expect(is_string($secret))->toBeTrue()
        ->and(strlen($secret))->toBeGreaterThan(10);
});

test('public oauth client creation does not flash client secret', function () {
    $user = User::factory()->withCompany('Acme')->create();

    $this->actingAs($user)
        ->post(route('company.oauth-apps.store'), [
            'name' => 'Public app',
            'redirect_uri' => 'https://example.com/callback',
            'is_public' => true,
        ])
        ->assertSessionMissing('oauth_client_credentials');
});

test('company developer cannot create oauth clients', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $developer = User::factory()->create();
    assignTeamRole($developer, 'company_developer', $company);

    $this->actingAs($developer)
        ->post(route('company.oauth-apps.store'), [
            'name' => 'Test',
            'redirect_uri' => 'https://example.com/callback',
        ])
        ->assertForbidden();
});
