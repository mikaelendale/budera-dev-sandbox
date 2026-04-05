<?php

namespace App\Services\Banking;

use App\Contracts\Banking\ColumnBankService;

/**
 * Sandbox Column-shaped adapter backed by the mock-bank HTTP client.
 */
class ColumnBankMock implements ColumnBankService
{
    public function __construct(
        private readonly MockBankClient $mockBank,
    ) {}

    public function health(): array
    {
        return $this->mockBank->health();
    }

    public function createAccount(string $currency = 'USD'): array
    {
        return $this->mockBank->createAccount($currency);
    }

    public function getBalance(string $accountId): array
    {
        return $this->mockBank->getBalance($accountId);
    }

    public function achPush(string $accountId, int $amountCents, ?string $idempotencyKey = null): array
    {
        return $this->mockBank->achPush($accountId, $amountCents, $idempotencyKey);
    }

    public function achPull(string $accountId, int $amountCents, ?string $idempotencyKey = null): array
    {
        return $this->mockBank->achPull($accountId, $amountCents, $idempotencyKey);
    }

    public function getTransfer(string $id): array
    {
        return $this->mockBank->getTransfer($id);
    }
}
