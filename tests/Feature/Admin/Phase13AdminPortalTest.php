<?php

use App\Models\Company;
use App\Models\ComplianceFlag;
use App\Models\DomainAuditLog;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WebhookOutbox;
use App\Notifications\Transactional\AccountFrozenNotification;
use App\States\WalletAccount\WalletAccountActive;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('non budera admin receives 403 on admin portal routes', function () {
    $user = User::factory()->create(['is_budera_admin' => false]);

    $routes = [
        ['GET', route('admin.kyb-reviews.index')],
        ['GET', route('admin.live-access.index')],
        ['GET', route('admin.companies.index')],
        ['GET', route('admin.compliance.index')],
        ['GET', route('admin.partner-banks.index')],
    ];

    foreach ($routes as [$method, $url]) {
        $this->actingAs($user)->call($method, $url)->assertForbidden();
    }
});

test('admin freezes and unfreezes company wallets and enqueues account webhooks', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $company = Company::factory()->create(['owner_id' => $owner->id]);

    $w1 = WalletAccount::factory()->active()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
    ]);
    $w2 = WalletAccount::factory()->paused()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
    ]);

    $admin = User::factory()->buderaAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.companies.freeze', $company))
        ->assertRedirect(route('admin.companies.show', $company));

    expect((string) $w1->fresh()->status)->toBe('frozen')
        ->and((string) $w2->fresh()->status)->toBe('frozen');

    $frozenEvents = WebhookOutbox::query()->where('event', 'account.frozen')->count();
    expect($frozenEvents)->toBe(2);

    Notification::assertSentToTimes($owner, AccountFrozenNotification::class, 2);

    $this->actingAs($admin)
        ->post(route('admin.companies.unfreeze', $company))
        ->assertRedirect(route('admin.companies.show', $company));

    expect($w1->fresh()->status)->toBeInstanceOf(WalletAccountActive::class)
        ->and($w2->fresh()->status)->toBeInstanceOf(WalletAccountActive::class);

    expect(WebhookOutbox::query()->where('event', 'account.unfrozen')->count())->toBe(2);
});

test('admin compliance inbox lists unresolved flags and resolve persists', function () {
    $flag = ComplianceFlag::factory()->create();

    $admin = User::factory()->buderaAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.compliance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/compliance/index')
            ->has('flags', 1));

    $this->actingAs($admin)
        ->get(route('admin.compliance.show', $flag))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('admin.compliance.resolve', $flag))
        ->assertRedirect(route('admin.compliance.index'));

    $flag->refresh();
    expect($flag->resolved_at)->not->toBeNull()
        ->and((int) $flag->resolved_by)->toBe((int) $admin->getKey());

    $this->actingAs($admin)
        ->get(route('admin.compliance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('flags', 0));
});

test('company owner can request live access after kyb approved', function () {
    $owner = User::factory()->withCompany('Live Req Co')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $company->update([
        'kyb_status' => 'approved',
        'live_enabled_at' => null,
    ]);

    $this->actingAs($owner)
        ->from(route('company.settings'))
        ->post(route('company.live-access.request'))
        ->assertRedirect(route('company.settings'))
        ->assertSessionHas('status');

    expect(DomainAuditLog::query()->where('action', 'live_access.requested')->exists())->toBeTrue();
});
