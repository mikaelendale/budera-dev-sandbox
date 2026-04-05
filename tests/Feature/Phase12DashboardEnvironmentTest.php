<?php

use App\Models\User;
use App\Models\WalletAccount;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

test('dashboard environment cookie is ignored for live when company is not live-enabled', function (): void {
    $owner = User::factory()->withCompany()->create();
    $company = $owner->firstCompany();
    expect($company)->not->toBeNull();
    expect($company->live_enabled_at)->toBeNull();

    WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $owner->getKey(),
        'environment' => 'sandbox',
        'balance_cents' => 100,
    ]);

    WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $owner->getKey(),
        'environment' => 'live',
        'balance_cents' => 900,
    ]);

    $cookieName = (string) config('budera.dashboard_environment_cookie');

    actingAs($owner)
        ->withCookie($cookieName, 'live')
        ->get(route('company.wallets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('company/wallets/index')
            ->has('wallets', 1)
            ->where('wallets.0.balance_cents', 100));
});

test('dashboard environment cookie live filters wallets when company is live-enabled', function (): void {
    $owner = User::factory()->withCompany()->create();
    $company = $owner->firstCompany();
    expect($company)->not->toBeNull();

    $company->forceFill(['live_enabled_at' => now()])->save();

    WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $owner->getKey(),
        'environment' => 'sandbox',
        'balance_cents' => 100,
    ]);

    WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $owner->getKey(),
        'environment' => 'live',
        'balance_cents' => 900,
    ]);

    $cookieName = (string) config('budera.dashboard_environment_cookie');

    actingAs($owner)
        ->withCookie($cookieName, 'live')
        ->get(route('company.wallets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('company/wallets/index')
            ->has('wallets', 1)
            ->where('wallets.0.balance_cents', 900));
});

test('posting live environment without live_enabled is rejected', function (): void {
    $owner = User::factory()->withCompany()->create();
    $company = $owner->firstCompany();
    expect($company)->not->toBeNull();
    expect($company->live_enabled_at)->toBeNull();

    actingAs($owner)
        ->post(route('company.dashboard.environment'), ['environment' => 'live'])
        ->assertSessionHas('error');
});
