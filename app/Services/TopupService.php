<?php

namespace App\Services;

use App\Contracts\Banking\ColumnBankService;
use App\Models\BankLink;
use App\Models\Topup;
use App\Models\WalletAccount;
use App\Services\Audit\AuthorizationLedgerService;
use App\Services\Audit\TransitionRecorder;
use App\States\BankLink\BankLinkVerified;
use App\States\Topup\TopupFailed;
use App\States\Topup\TopupPending;
use App\States\Topup\TopupProcessing;
use App\States\WalletAccount\WalletAccountActive;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class TopupService
{
    public function __construct(
        private readonly ColumnBankService $columnBank,
        private readonly AuthorizationLedgerService $authorizationLedger,
        private readonly TransitionRecorder $transitionRecorder,
    ) {}

    public function createAchTopup(
        WalletAccount $wallet,
        BankLink $bankLink,
        int $amountCents,
        ?string $idempotencyKey,
        ?int $authorizationLedgerEntryId = null,
    ): Topup {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('amount_cents must be positive.');
        }

        if (! $wallet->status instanceof WalletAccountActive) {
            throw new InvalidArgumentException('wallet_not_active');
        }

        if (! $bankLink->status instanceof BankLinkVerified) {
            throw new InvalidArgumentException('bank_link_not_verified');
        }

        if ((int) $bankLink->user_id !== (int) $wallet->user_id) {
            throw new InvalidArgumentException('bank_link_user_mismatch');
        }

        if ((string) $bankLink->environment !== (string) $wallet->environment) {
            throw new InvalidArgumentException('environment_mismatch');
        }

        $partnerAccountId = (string) $wallet->partner_account_id;
        if ($partnerAccountId === '') {
            throw new RuntimeException('wallet_missing_partner_account_id');
        }

        $resolvedAuthId = $this->authorizationLedger->resolveLedgerIdForAchTopup($bankLink, $authorizationLedgerEntryId);

        $amountUsd = round($amountCents / 100, 2);

        return DB::transaction(function () use (
            $wallet,
            $bankLink,
            $amountCents,
            $amountUsd,
            $idempotencyKey,
            $partnerAccountId,
            $resolvedAuthId
        ): Topup {
            $topup = Topup::query()->create([
                'wallet_account_id' => $wallet->getKey(),
                'bank_link_id' => $bankLink->getKey(),
                'authorization_ledger_entry_id' => $resolvedAuthId,
                'environment' => $wallet->environment,
                'status' => TopupPending::class,
                'amount_usd' => $amountUsd,
                'idempotency_key' => $idempotencyKey,
                'metadata' => [],
            ]);

            $fromPending = $topup->status->getValue();

            $bankKey = $idempotencyKey !== null && $idempotencyKey !== ''
                ? 'top_'.$idempotencyKey
                : 'top_'.$topup->getKey();

            try {
                $bankResponse = $this->columnBank->achPull(
                    $partnerAccountId,
                    $amountCents,
                    $bankKey,
                );
            } catch (\Throwable $e) {
                $topup->status->transitionTo(TopupFailed::class);
                $metadata = is_array($topup->metadata) ? $topup->metadata : [];
                $metadata['bank_error'] = $e->getMessage();
                $topup->metadata = $metadata;
                $topup->save();

                $failed = $topup->fresh();
                if ($failed !== null) {
                    $this->transitionRecorder->record(
                        $failed,
                        $fromPending,
                        'failed',
                        [
                            'stream' => 'developer',
                            'actor_type' => 'system',
                            'actor_id' => null,
                            'action' => 'topup.bank_pull_failed',
                            'resource_type' => 'topups',
                            'resource_id' => (string) $failed->getKey(),
                            'environment' => $failed->environment,
                            'account_id' => (int) $wallet->getKey(),
                            'metadata' => [
                                'company_id' => (string) $wallet->company_id,
                                'wallet_account_id' => (string) $wallet->getKey(),
                                'authorization_ledger_entry_id' => (string) $resolvedAuthId,
                            ],
                        ],
                    );
                }

                return $topup->fresh();
            }

            $transferId = isset($bankResponse['transfer_id']) ? (string) $bankResponse['transfer_id'] : null;
            $metadata = is_array($topup->metadata) ? $topup->metadata : [];
            $metadata['bank_transfer_id'] = $transferId;
            $metadata['bank_response'] = $bankResponse;
            $topup->metadata = $metadata;

            $topup->status->transitionTo(TopupProcessing::class);
            $topup->save();

            $processing = $topup->fresh();
            if ($processing !== null) {
                $this->transitionRecorder->record(
                    $processing,
                    $fromPending,
                    'processing',
                    [
                        'stream' => 'developer',
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'action' => 'topup.processing',
                        'resource_type' => 'topups',
                        'resource_id' => (string) $processing->getKey(),
                        'environment' => $processing->environment,
                        'account_id' => (int) $wallet->getKey(),
                        'metadata' => [
                            'company_id' => (string) $wallet->company_id,
                            'wallet_account_id' => (string) $wallet->getKey(),
                            'authorization_ledger_entry_id' => (string) $resolvedAuthId,
                            'bank_transfer_id' => $transferId,
                        ],
                    ],
                );
            }

            return $topup->fresh();
        });
    }
}
