<?php

namespace App\Services\Ledger;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public function __construct(int $requestedAmountCents, int $availableBalanceCents)
    {
        parent::__construct(
            "Insufficient balance: requested {$requestedAmountCents} cents, available {$availableBalanceCents} cents."
        );
    }
}
