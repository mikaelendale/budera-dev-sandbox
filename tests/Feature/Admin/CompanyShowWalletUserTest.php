<?php

use App\Models\Company;
use App\Models\User;
use App\Models\WalletAccount;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin company show includes wallet end user in inertia props', function () {
    $owner = User::factory()->create();
    $company = Company::factory()->create(['owner_id' => $owner->id]);

    $endUser = User::factory()->create([
        'name' => 'End User Person',
        'email' => 'enduser-wallet@example.test',
    ]);

    WalletAccount::factory()->active()->create([
        'company_id' => $company->id,
        'user_id' => $endUser->id,
    ]);

    $admin = User::factory()->buderaAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.companies.show', $company))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/companies/show')
            ->has('wallets', 1)
            ->where('wallets.0.user.email', 'enduser-wallet@example.test')
            ->where('wallets.0.user.name', 'End User Person')
            ->where('wallets.0.user_id', (int) $endUser->getKey()));
});
