<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Signature('budera:sync-company-permissions')]
#[Description('Re-sync team-scoped Spatie roles + permissions for every company (idempotent)')]
class SyncCompanyPermissionsCommand extends Command
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

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPerms = array_unique(array_merge(self::OWNER_PERMISSIONS, self::DEVELOPER_PERMISSIONS));

        foreach ($allPerms as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $companies = Company::query()->select('id', 'name')->get();

        $teamKey = config('permission.column_names.team_foreign_key');

        foreach ($companies as $company) {
            setPermissionsTeamId($company->getKey());

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

            $this->info("Synced permissions for company {$company->id} ({$company->name})");
        }

        setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Done — '.$companies->count().' company(s) processed.');

        return self::SUCCESS;
    }
}
