<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\BuderaAdminSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

test('role seeder creates canonical roles and permissions', function () {
    $this->seed(RoleSeeder::class);

    $roles = [
        'budera_admin',
        'company_owner',
        'company_developer',
        'end_user',
        'bank_partner',
        'agent',
    ];

    foreach ($roles as $roleName) {
        expect(Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists())->toBeTrue();
    }

    expect(Permission::query()->count())->toBeGreaterThanOrEqual(25);

    $adminRole = Role::findByName('budera_admin', 'web');
    expect($adminRole->hasPermissionTo('admin.companies.view'))->toBeTrue();
    expect($adminRole->hasPermissionTo('admin.audit.view'))->toBeTrue();
    expect($adminRole->hasPermissionTo('admin.compliance.manage'))->toBeTrue();
});

test('budera admin seeder assigns budera_admin role to admin user', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(BuderaAdminSeeder::class);

    $admin = User::query()->where('email', env('BUDERA_ADMIN_EMAIL', 'budera-admin@local.test'))->first();

    expect($admin)->not()->toBeNull();
    expect($admin?->is_budera_admin)->toBeTrue();

    setPermissionsTeamId(0);
    expect($admin?->hasRole('budera_admin'))->toBeTrue();
    setPermissionsTeamId(null);
});

test('hasCompanyPermission returns false when permission row is missing from database', function () {
    $this->seed(RoleSeeder::class);
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    expect($owner->hasCompanyPermission($company, 'company.members.manage'))->toBeTrue();

    Permission::query()->where('name', 'company.members.manage')->where('guard_name', 'web')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($owner->hasCompanyPermission($company, 'company.members.manage'))->toBeFalse();
});
