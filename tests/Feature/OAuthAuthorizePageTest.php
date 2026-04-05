<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\ClientRepository;

test('oauth consent screen exposes csrf for native approve and deny forms', function (): void {
    $user = User::factory()->withCompany()->create();
    $company = $user->firstCompany();
    expect($company)->not->toBeNull();

    $redirectUri = 'https://example.com/oauth/callback';

    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Test OAuth App',
        [$redirectUri],
        true,
        $user,
    );
    $client->forceFill(['company_id' => $company->getKey()])->save();

    $query = http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'wallet:read',
        'state' => 'test-state',
    ]);

    $this->actingAs($user)
        ->get('/oauth/authorize?'.$query)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('oauth/authorize')
            ->has('csrfToken')
            ->has('authToken')
            ->where('approveAction', route('passport.authorizations.approve'))
            ->where('denyAction', route('passport.authorizations.deny')));
});
