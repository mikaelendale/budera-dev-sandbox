<?php

use App\Models\User;
use App\Models\WalletAccount;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

test('dashboard renders with deferred groups for company member', function (): void {
    $owner = User::factory()->withCompany()->create();
    $company = $owner->firstCompany();
    expect($company)->not->toBeNull();

    WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $owner->getKey(),
        'environment' => 'sandbox',
        'balance_cents' => 500,
    ]);

    actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard'));
});
