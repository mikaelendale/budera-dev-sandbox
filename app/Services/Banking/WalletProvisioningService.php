<?php

namespace App\Services\Banking;

use App\Contracts\Banking\ColumnBankService;
use App\Contracts\Kyc\KycProvider;
use App\Models\Company;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use App\Notifications\Transactional\KycApprovedNotification;
use App\Services\Audit\TransitionRecorder;
use App\Services\Mail\TransactionalMail;
use App\States\WalletAccount\WalletAccountActive;
use App\States\WalletKycVerification\WalletKycVerificationApproved;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class WalletProvisioningService
{
    public function __construct(
        private readonly ColumnBankService $columnBank,
        private readonly KycProvider $kycProvider,
        private readonly TransitionRecorder $transitionRecorder,
    ) {}

    public function provision(Company $company, User $user): WalletAccount
    {
        return DB::transaction(function () use ($company, $user): WalletAccount {
            return WalletAccount::query()->create([
                'company_id' => $company->getKey(),
                'user_id' => $user->getKey(),
                'environment' => 'sandbox',
                'status' => 'pending',
                'partner_account_id' => null,
                'metadata' => [],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submitKyc(WalletAccount $walletAccount, array $payload): WalletKycVerification
    {
        $res = $this->kycProvider->submitSubmission($walletAccount, $payload);

        return WalletKycVerification::query()->create([
            'wallet_account_id' => $walletAccount->getKey(),
            'status' => 'pending',
            'mock_kyc_submission_id' => (string) ($res['id'] ?? ''),
            'submitted_payload' => $payload,
        ]);
    }

    /**
     * After KYC is approved (webhook or sandbox), create the partner bank account and activate the wallet.
     */
    public function activateWalletPartnerAccountAfterKyc(WalletKycVerification $kyc): void
    {
        DB::transaction(function () use ($kyc): void {
            $wallet = WalletAccount::query()
                ->whereKey($kyc->wallet_account_id)
                ->lockForUpdate()
                ->firstOrFail();

            $partnerId = $wallet->partner_account_id;
            if (is_string($partnerId) && $partnerId !== '' && $wallet->status instanceof WalletAccountActive) {
                return;
            }

            $created = $this->columnBank->createAccount('USD');
            $newPartnerId = (string) ($created['id'] ?? '');
            if ($newPartnerId === '') {
                throw new RuntimeException('bank_account_create_failed');
            }

            $wallet->partner_account_id = $newPartnerId;
            $wallet->save();

            if (! $wallet->status instanceof WalletAccountActive) {
                $wallet = $wallet->status->transitionTo(WalletAccountActive::class);
            }

            $companyId = (string) $wallet->company_id;

            $this->transitionRecorder->record(
                $wallet,
                'pending',
                'active',
                [
                    'stream' => 'agent_bank',
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'action' => 'wallet.account.activated',
                    'resource_type' => 'wallet_accounts',
                    'resource_id' => (string) $wallet->getKey(),
                    'environment' => $wallet->environment,
                    'metadata' => [
                        'company_id' => $companyId,
                        'user_id' => (string) $wallet->user_id,
                        'partner_account_id' => $wallet->partner_account_id,
                        'wallet_kyc_verification_id' => (string) $kyc->getKey(),
                    ],
                    'webhook_event' => 'account.active',
                    'webhook_payload' => [
                        'event' => 'account.active',
                        'data' => [
                            'wallet_account_id' => (string) $wallet->public_id,
                            'partner_account_id' => $wallet->partner_account_id,
                            'company_id' => $companyId,
                        ],
                    ],
                ],
            );

            $user = $wallet->user()->first();
            $kycFresh = WalletKycVerification::query()->whereKey($kyc->getKey())->firstOrFail();
            TransactionalMail::notifyUser($user, new KycApprovedNotification($wallet, $kycFresh));
        });
    }

    /**
     * Sandbox-only: mark verification approved and provision partner account + active wallet.
     *
     * @throws Throwable
     */
    public function forceApproveKycForSandbox(WalletKycVerification $kyc): void
    {
        DB::transaction(function () use ($kyc): void {
            $fresh = WalletKycVerification::query()
                ->whereKey($kyc->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->status instanceof WalletKycVerificationApproved) {
                $from = $fresh->status->getValue();
                $fresh->status->transitionTo(WalletKycVerificationApproved::class);
                $fresh->verified_at = now();
                $fresh->save();

                $kycDone = $fresh->fresh();
                $walletAccount = $kycDone?->walletAccount()->first();
                if ($kycDone !== null) {
                    $this->transitionRecorder->record(
                        $kycDone,
                        $from,
                        'approved',
                        [
                            'stream' => 'developer',
                            'actor_type' => 'system',
                            'actor_id' => null,
                            'action' => 'wallet.kyc.sandbox_force_approved',
                            'resource_type' => 'wallet_kyc_verifications',
                            'resource_id' => (string) $kycDone->getKey(),
                            'environment' => $walletAccount?->environment ?? 'sandbox',
                            'account_id' => $walletAccount !== null ? (int) $walletAccount->getKey() : null,
                            'metadata' => [
                                'wallet_account_id' => (string) $kycDone->wallet_account_id,
                                'mock_kyc_submission_id' => $kycDone->mock_kyc_submission_id,
                            ],
                        ],
                    );
                }
            }

            $this->activateWalletPartnerAccountAfterKyc($fresh->fresh());
        });
    }
}
