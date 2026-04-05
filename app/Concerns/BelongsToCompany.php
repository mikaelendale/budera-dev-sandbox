<?php

namespace App\Concerns;

use App\Models\Company;
use App\Scopes\CompanyScope;
use App\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeWithoutCompanyScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(CompanyScope::class);
    }

    public function scopeForEnvironment(Builder $query, ?string $environment = null): Builder
    {
        $resolvedEnvironment = $environment;

        if ($resolvedEnvironment === null && app()->bound(CompanyContext::class)) {
            /** @var CompanyContext $context */
            $context = app(CompanyContext::class);
            $resolvedEnvironment = $context->environment();
        }

        if ($resolvedEnvironment === null) {
            return $query;
        }

        if (! in_array('environment', $this->getFillable(), true)) {
            return $query;
        }

        return $query->where($this->qualifyColumn('environment'), $resolvedEnvironment);
    }
}
