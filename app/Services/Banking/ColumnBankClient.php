<?php

namespace App\Services\Banking;

use App\Contracts\Banking\ColumnBankService;

/**
 * Live Column API (TBD). Wired when `PartnerBankIntegrationResolver::useLiveColumnClient()` is true.
 */
class ColumnBankClient implements ColumnBankService
{
    public function health(): array
    {
        throw new \RuntimeException('Column live API not yet implemented.');
    }

    public function createAccount(string $currency = 'USD'): array
    {
        throw new \RuntimeException('Column live API not yet implemented.');
    }

    public function getBalance(string $accountId): array
    {
        throw new \RuntimeException('Column live API not yet implemented.');
    }

    public function achPush(string $accountId, int $amountCents, ?string $idempotencyKey = null): array
    {
        throw new \RuntimeException('Column live API not yet implemented.');
    }

    public function achPull(string $accountId, int $amountCents, ?string $idempotencyKey = null): array
    {
        throw new \RuntimeException('Column live API not yet implemented.');
    }

    public function getTransfer(string $id): array
    {
        throw new \RuntimeException('Column live API not yet implemented.');
    }
}
