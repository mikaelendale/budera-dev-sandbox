<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Canonical role names (see docs/budera-dev-timeline.md §02):
 * - company_owner, company_developer: created per company via CompanyObserver when a Company is created.
 * - budera_admin: set User::$is_budera_admin in the database (or a future internal admin UI), not via public registration.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'admin.kyb.approve',
            'admin.accounts.freeze',
            'admin.keys.live.issue',
            'admin.companies.view',
            'admin.wallets.view',
            'admin.payments.view',
            'admin.audit.view',
            'admin.compliance.manage',
            'admin.users.view',
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
            'end_user.bank_links.manage',
            'end_user.payment_approvals.manage',
            'end_user.agent_access.revoke',
            'bank_partner.transactions.view',
            'bank_partner.kyb_docs.view',
            'bank_partner.reconciliation.view',
            'agent.wallet.read',
            'agent.payments.create',
            'agent.topups.create',
            'agent.transfers.create',
            'agent.ledger.read',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $matrix = [
            'budera_admin' => [
                'admin.kyb.approve',
                'admin.accounts.freeze',
                'admin.keys.live.issue',
                'admin.companies.view',
                'admin.wallets.view',
                'admin.payments.view',
                'admin.audit.view',
                'admin.compliance.manage',
                'admin.users.view',
            ],
            'company_owner' => [
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
            ],
            'company_developer' => [
                'company.keys.view',
                'company.logs.view',
                'company.sandbox.use',
                'company.wallets.view',
                'company.webhooks.manage',
            ],
            'end_user' => [
                'end_user.bank_links.manage',
                'end_user.payment_approvals.manage',
                'end_user.agent_access.revoke',
            ],
            'bank_partner' => [
                'bank_partner.transactions.view',
                'bank_partner.kyb_docs.view',
                'bank_partner.reconciliation.view',
            ],
            'agent' => [
                'agent.wallet.read',
                'agent.payments.create',
                'agent.topups.create',
                'agent.transfers.create',
                'agent.ledger.read',
            ],
        ];

        foreach ($matrix as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($rolePermissions);
        }
    }
}
