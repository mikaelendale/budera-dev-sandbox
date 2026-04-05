<?php

namespace App\Services;

use App\Models\Transfer;
use App\Models\WalletAccount;
use App\Services\Audit\TransitionRecorder;
use App\Services\Ledger\LedgerService;
use App\States\Transfer\TransferCompleted;
use App\States\Transfer\TransferPending;
use App\States\WalletAccount\WalletAccountActive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransferService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly TransitionRecorder $transitionRecorder,
    ) {}

    public function createBookTransfer(
        WalletAccount $from,
        WalletAccount $to,
        int $amountCents,
        ?string $idempotencyKey,
    ): Transfer {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('amount_cents must be positive.');
        }

        if ($from->getKey() === $to->getKey()) {
            throw new InvalidArgumentException('same_wallet');
        }

        if ((int) $from->company_id !== (int) $to->company_id) {
            throw new InvalidArgumentException('company_mismatch');
        }

        if ((string) $from->environment !== (string) $to->environment) {
            throw new InvalidArgumentException('environment_mismatch');
        }

        if (! $from->status instanceof WalletAccountActive || ! $to->status instanceof WalletAccountActive) {
            throw new InvalidArgumentException('wallet_not_active');
        }

        $amountUsd = round($amountCents / 100, 2);

        return DB::transaction(function () use ($from, $to, $amountCents, $amountUsd, $idempotencyKey): Transfer {
            $fromWallet = $from;
            $toWallet = $to;

            $transfer = Transfer::query()->create([
                'from_wallet_account_id' => $fromWallet->getKey(),
                'to_wallet_account_id' => $toWallet->getKey(),
                'environment' => $fromWallet->environment,
                'status' => TransferPending::class,
                'amount_usd' => $amountUsd,
                'idempotency_key' => $idempotencyKey,
                'metadata' => [],
            ]);

            $refId = (string) Str::uuid();

            $this->ledger->debit(
                $fromWallet->fresh(),
                $amountCents,
                'transfer',
                $refId,
                'Book transfer to wallet '.(string) $toWallet->getKey(),
            );

            $this->ledger->credit(
                $toWallet->fresh(),
                $amountCents,
                'transfer',
                $refId,
                'Book transfer from wallet '.(string) $fromWallet->getKey(),
            );

            $metadata = is_array($transfer->metadata) ? $transfer->metadata : [];
            $metadata['ledger_reference_id'] = $refId;
            $transfer->metadata = $metadata;

            $fromState = $transfer->status->getValue();
            $transfer->status->transitionTo(TransferCompleted::class);
            $transfer->save();

            $done = $transfer->fresh();
            if ($done !== null) {
                $this->transitionRecorder->record(
                    $done,
                    $fromState,
                    'completed',
                    [
                        'stream' => 'developer',
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'action' => 'transfer.completed',
                        'resource_type' => 'transfers',
                        'resource_id' => (string) $done->getKey(),
                        'environment' => $done->environment,
                        'account_id' => (int) $fromWallet->getKey(),
                        'metadata' => [
                            'company_id' => (string) $fromWallet->company_id,
                            'from_wallet_account_id' => (string) $fromWallet->getKey(),
                            'to_wallet_account_id' => (string) $toWallet->getKey(),
                            'ledger_reference_id' => $refId,
                        ],
                    ],
                );
            }

            return $transfer->fresh();
        });
    }
}
