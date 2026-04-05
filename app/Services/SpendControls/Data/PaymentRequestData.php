<?php

namespace App\Services\SpendControls\Data;

use App\Models\Payment;
use App\Models\WalletAccount;

readonly class PaymentRequestData
{
    public function __construct(
        public WalletAccount $walletAccount,
        public int $amountCents,
        public ?string $category = null,
        public ?string $payeeRef = null,
        public ?string $requestedByType = null,
        public ?string $requestedById = null,
        public ?Payment $payment = null,
    ) {}

    public function amountUsd(): float
    {
        return $this->amountCents / 100;
    }
}
