<?php

use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $hasPersonal = OAuthClient::query()
        ->where('revoked', false)
        ->get()
        ->contains(fn (OAuthClient $c): bool => $c->hasGrantType('personal_access'));

    if (! $hasPersonal) {
        OAuthClient::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Personal Access Client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => [],
            'grant_types' => ['personal_access'],
            'revoked' => false,
        ]);
    }
});

test('user can view oauth connections settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('oauth-connections.edit'))
        ->assertOk();
});

test('user can revoke an oauth token from settings', function () {
    $user = User::factory()->create();

    $tokenResult = $user->createToken('Test integration', ['wallet:read']);
    $tokenId = $tokenResult->token->id;

    $this->actingAs($user)
        ->from(route('oauth-connections.edit'))
        ->delete(route('oauth-connections.destroy', ['token' => $tokenId]))
        ->assertRedirect();

    expect($tokenResult->token->fresh()?->revoked)->toBeTrue();
});
