<?php

namespace App\Services\Kyc;

use App\Contracts\Kyc\KycProvider;
use App\Models\WalletAccount;
use App\Services\Banking\MockBankClient;

class MockKycProvider implements KycProvider
{
    public function __construct(
        private readonly MockBankClient $mockBank,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function submitSubmission(WalletAccount $walletAccount, array $payload): array
    {
        $body = $payload;
        $partnerId = $walletAccount->partner_account_id;
        if (is_string($partnerId) && $partnerId !== '') {
            $body['account_id'] = $partnerId;
        }

        return $this->mockBank->submitKycSubmission($body);
    }
}
