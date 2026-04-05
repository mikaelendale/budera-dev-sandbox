<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Auth\WalletAgentAccess;
use App\Tenancy\CompanyContext;

class WalletAccountPolicy
{
    public function view(User $user, WalletAccount $walletAccount): bool
    {
        return WalletAgentAccess::canAccessWallet(request(), $walletAccount);
    }

    public function viewAnyAsCompanyMember(User $user): bool
    {
        $company = $user->firstCompany();

        if ($company === null) {
            return false;
        }

        return $user->hasCompanyPermission($company, 'company.wallets.view');
    }

    public function viewAsCompanyMember(User $user, WalletAccount $walletAccount): bool
    {
        if (! app()->bound(CompanyContext::class)) {
            return false;
        }

        /** @var CompanyContext $context */
        $context = app(CompanyContext::class);
        $companyId = $context->companyId();

        if ($companyId === null || (int) $walletAccount->company_id !== (int) $companyId) {
            return false;
        }

        $env = $context->environment();

        if ($env !== null && (string) $walletAccount->environment !== (string) $env) {
            return false;
        }

        $company = Company::query()->find($companyId);

        if ($company === null) {
            return false;
        }

        return $user->hasCompanyPermission($company, 'company.wallets.view');
    }

    public function updatePolicy(User $user, WalletAccount $walletAccount): bool
    {
        if (! app()->bound(CompanyContext::class)) {
            return false;
        }

        /** @var CompanyContext $context */
        $context = app(CompanyContext::class);
        $companyId = $context->companyId();

        if ($companyId === null || (int) $walletAccount->company_id !== (int) $companyId) {
            return false;
        }

        $env = $context->environment();
        if ($env !== null && (string) $walletAccount->environment !== (string) $env) {
            return false;
        }

        $company = Company::query()->find($companyId);

        if ($company === null) {
            return false;
        }

        return $user->hasCompanyPermission($company, 'company.wallets.manage');
    }
}
