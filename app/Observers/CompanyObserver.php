<?php

namespace App\Observers;

use App\Models\Company;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CompanyObserver
{
    /**
     * @var list<string>
     */
    private const OWNER_PERMISSIONS = [
        'company.settings.manage',
        'company.members.manage',
        'company.keys.view',
        'company.keys.manage',
        'company.wallets.view',
        'company.wallets.manage',
        'company.payments.approve',
        'company.webhooks.manage',
        'company.logs.view',
        'company.sandbox.use',
    ];

    /**
     * @var list<string>
     */
    private const DEVELOPER_PERMISSIONS = [
        'company.keys.view',
        'company.logs.view',
        'company.sandbox.use',
        'company.wallets.view',
        'company.webhooks.manage',
    ];

    public function created(Company $company): void
    {
        setPermissionsTeamId($company->getKey());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $teamKey = config('permission.column_names.team_foreign_key');

        $allPerms = array_unique(array_merge(self::OWNER_PERMISSIONS, self::DEVELOPER_PERMISSIONS));

        foreach ($allPerms as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $owner = Role::query()
            ->where('name', 'company_owner')
            ->where('guard_name', 'web')
            ->where($teamKey, $company->getKey())
            ->first();

        if (! $owner) {
            $owner = Role::query()->create([
                'name' => 'company_owner',
                'guard_name' => 'web',
                $teamKey => $company->getKey(),
            ]);
        }

        $developer = Role::query()
            ->where('name', 'company_developer')
            ->where('guard_name', 'web')
            ->where($teamKey, $company->getKey())
            ->first();

        if (! $developer) {
            $developer = Role::query()->create([
                'name' => 'company_developer',
                'guard_name' => 'web',
                $teamKey => $company->getKey(),
            ]);
        }

        $owner->syncPermissions(self::OWNER_PERMISSIONS);
        $developer->syncPermissions(self::DEVELOPER_PERMISSIONS);

        setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
