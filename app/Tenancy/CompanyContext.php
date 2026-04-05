<?php

namespace App\Tenancy;

class CompanyContext
{
    public function __construct(
        private readonly ?int $companyId = null,
        private readonly ?string $environment = null,
        private readonly bool $bypassCompanyScope = false,
    ) {}

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    public function bypassesCompanyScope(): bool
    {
        return $this->bypassCompanyScope;
    }

    public function environment(): ?string
    {
        return $this->environment;
    }
}
