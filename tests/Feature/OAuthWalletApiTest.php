<?php

use App\Models\User;
use Laravel\Passport\Passport;

test('wallet me requires authentication', function () {
    $this->getJson('/api/v1/wallet/me')->assertUnauthorized();
});

test('wallet me returns json when token has wallet:read scope', function () {
    $user = User::factory()->create();

    Passport::actingAs($user, ['wallet:read']);

    $response = $this->getJson('/api/v1/wallet/me');

    $response->assertOk()
        ->assertJsonStructure(['wallet', 'scopes'])
        ->assertJsonMissingPath('user_id');
});

test('wallet me is forbidden without wallet:read scope', function () {
    $user = User::factory()->create();

    Passport::actingAs($user, ['wallet:pay']);

    $this->getJson('/api/v1/wallet/me')->assertForbidden();
});
