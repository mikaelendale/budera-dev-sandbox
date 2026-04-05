<?php

namespace App\Scopes;

use App\Models\WalletAccount;
use App\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound(CompanyContext::class)) {
            return;
        }

        /** @var CompanyContext $context */
        $context = app(CompanyContext::class);

        if ($context->bypassesCompanyScope()) {
            return;
        }

        $companyId = $context->companyId();

        if ($companyId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $companyId);

        $environment = $context->environment();

        if ($environment === null) {
            return;
        }

        if (! $model instanceof WalletAccount) {
            return;
        }

        if (! in_array('environment', $model->getFillable(), true)) {
            return;
        }

        $builder->where($model->qualifyColumn('environment'), $environment);
    }
}
