<?php

namespace App\Contracts\Banking;

/**
 * Partner-bank abstraction (timeline: Column-style contract). Implementations: sandbox mock vs live HTTP.
 */
interface ColumnBankService
{
    /**
     * @return array<string, mixed>
     */
    public function health(): array;

    /**
     * @return array<string, mixed>
     */
    public function createAccount(string $currency = 'USD'): array;

    /**
     * @return array<string, mixed>
     */
    public function getBalance(string $accountId): array;

    /**
     * @return array<string, mixed>
     */
    public function achPush(string $accountId, int $amountCents, ?string $idempotencyKey = null): array;

    /**
     * @return array<string, mixed>
     */
    public function achPull(string $accountId, int $amountCents, ?string $idempotencyKey = null): array;

    /**
     * @return array<string, mixed>
     */
    public function getTransfer(string $id): array;
}
