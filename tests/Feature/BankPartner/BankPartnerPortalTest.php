<?php

use App\Models\Company;
use App\Models\KybReview;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\WalletAccount;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function createBankPartner(): User
{
    $user = User::factory()->create();

    setPermissionsTeamId(0);
    $user->assignRole('bank_partner');
    setPermissionsTeamId(null);

    return $user;
}

test('non bank partner user gets 403 on all bank-partner routes', function () {
    $user = User::factory()->create();

    $routes = [
        route('bank-partner.dashboard'),
        route('bank-partner.transactions.index'),
        route('bank-partner.transactions.export'),
        route('bank-partner.kyb-documents.index'),
        route('bank-partner.reconciliation.index'),
    ];

    foreach ($routes as $url) {
        $this->actingAs($user)->get($url)->assertForbidden();
    }
});

test('bank partner user can view dashboard', function () {
    $partner = createBankPartner();
    $company = Company::factory()->create();

    WalletAccount::factory()->active()->create([
        'company_id' => $company->id,
        'balance_cents' => 50000,
    ]);

    WalletAccount::factory()->create([
        'company_id' => $company->id,
        'balance_cents' => 0,
    ]);

    $this->actingAs($partner)
        ->get(route('bank-partner.dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bank-partner/dashboard')
            ->has('stats')
            ->where('stats.total_accounts', 2)
            ->where('stats.active_accounts', 1)
            ->where('stats.total_balance_cents', 50000)
            ->where('stats.total_companies', 1));
});

test('bank partner user can view transactions', function () {
    $partner = createBankPartner();
    $wallet = WalletAccount::factory()->active()->create();

    LedgerEntry::factory()->credit()->create([
        'wallet_account_id' => $wallet->id,
    ]);

    $this->actingAs($partner)
        ->get(route('bank-partner.transactions.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bank-partner/transactions')
            ->has('entries.data', 1)
            ->has('filters'));
});

test('bank partner user can export transactions csv', function () {
    $partner = createBankPartner();
    $wallet = WalletAccount::factory()->active()->create();

    LedgerEntry::factory()->credit()->create([
        'wallet_account_id' => $wallet->id,
    ]);

    $response = $this->actingAs($partner)
        ->get(route('bank-partner.transactions.export'))
        ->assertSuccessful();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

test('bank partner user can view kyb documents', function () {
    $partner = createBankPartner();
    $company = Company::factory()->create();

    KybReview::factory()->create(['company_id' => $company->id]);

    $this->actingAs($partner)
        ->get(route('bank-partner.kyb-documents.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bank-partner/kyb-documents')
            ->has('reviews.data', 1));
});

test('bank partner user can view reconciliation', function () {
    $partner = createBankPartner();
    $wallet = WalletAccount::factory()->active()->create([
        'balance_cents' => 10000,
    ]);

    LedgerEntry::factory()->credit(10000, 10000)->create([
        'wallet_account_id' => $wallet->id,
    ]);

    $this->actingAs($partner)
        ->get(route('bank-partner.reconciliation.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bank-partner/reconciliation')
            ->has('wallets.data', 1));
});

test('bank partner cannot access admin routes', function () {
    $partner = createBankPartner();

    $adminRoutes = [
        route('admin.kyb-reviews.index'),
        route('admin.live-access.index'),
        route('admin.companies.index'),
        route('admin.compliance.index'),
        route('admin.partner-banks.index'),
    ];

    foreach ($adminRoutes as $url) {
        $this->actingAs($partner)->get($url)->assertForbidden();
    }
});

test('bank partner cannot access company dashboard routes', function () {
    $partner = createBankPartner();

    $response = $this->actingAs($partner)->get(route('dashboard'));

    expect($response->status())->not->toBe(200);
});
