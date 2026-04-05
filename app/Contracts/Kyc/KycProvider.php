<?php

namespace App\Contracts\Kyc;

use App\Models\WalletAccount;

interface KycProvider
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function submitSubmission(WalletAccount $walletAccount, array $payload): array;
}
