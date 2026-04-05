<?php

namespace App\Contracts\BankLink;

use App\Models\BankLink;
use App\Models\Company;
use App\Models\User;

interface BankLinkService
{
    /**
     * @return array{plain_session_token: string, bankLink: BankLink}
     */
    public function createHostedSession(User $endUser, Company $company, string $environment): array;

    /**
     * @param  array{routing_number: string, account_number: string, bank_slug?: string|null}  $credentials
     */
    public function submitCredentials(BankLink $link, array $credentials): BankLink;

    /**
     * @param  array{routing_number: string, account_number: string, bank_slug?: string|null}  $credentials
     */
    public function startSession(User $user, string $environment, array $credentials, ?int $companyId = null): BankLink;

    public function verifyMicrodeposits(BankLink $link, User $actor, int $amountFirstCents, int $amountSecondCents): BankLink;

    public function revoke(BankLink $link, User $actor): BankLink;
}
