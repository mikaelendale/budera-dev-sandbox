<?php

namespace App\Services\Ledger;

use App\Models\LedgerEntry;
use App\Models\WalletAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerService
{
    public function debit(
        WalletAccount $wallet,
        int $amountCents,
        string $refType,
        string $refId,
        ?string $description = null
    ): LedgerEntry {
        return $this->postEntry(
            wallet: $wallet,
            type: 'debit',
            amountCents: $amountCents,
            refType: $refType,
            refId: $refId,
            description: $description,
        );
    }

    public function credit(
        WalletAccount $wallet,
        int $amountCents,
        string $refType,
        string $refId,
        ?string $description = null
    ): LedgerEntry {
        return $this->postEntry(
            wallet: $wallet,
            type: 'credit',
            amountCents: $amountCents,
            refType: $refType,
            refId: $refId,
            description: $description,
        );
    }

    public function reversal(LedgerEntry $original, string $reason): LedgerEntry
    {
        $wallet = $original->walletAccount()->firstOrFail();
        $reversalType = $original->type === 'credit' ? 'debit' : 'credit';

        return $this->postEntry(
            wallet: $wallet,
            type: $reversalType,
            amountCents: (int) $original->amount_cents,
            refType: 'reversal',
            refId: (string) Str::uuid(),
            description: $reason,
            metadata: [
                'reverses_ledger_entry_id' => (int) $original->getKey(),
                'reverses_reference_type' => (string) $original->reference_type,
                'reverses_reference_id' => (string) $original->reference_id,
            ],
        );
    }

    public function balanceForAccount(WalletAccount $wallet): int
    {
        $credits = (int) LedgerEntry::query()
            ->where('wallet_account_id', $wallet->getKey())
            ->where('type', 'credit')
            ->sum('amount_cents');
        $debits = (int) LedgerEntry::query()
            ->where('wallet_account_id', $wallet->getKey())
            ->where('type', 'debit')
            ->sum('amount_cents');

        return $credits - $debits;
    }

    private function postEntry(
        WalletAccount $wallet,
        string $type,
        int $amountCents,
        string $refType,
        string $refId,
        ?string $description = null,
        array $metadata = []
    ): LedgerEntry {
        if (! in_array($type, ['debit', 'credit'], true)) {
            throw new \InvalidArgumentException("Unsupported ledger entry type [{$type}].");
        }

        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Amount cents must be greater than zero.');
        }

        return DB::transaction(function () use (
            $wallet,
            $type,
            $amountCents,
            $refType,
            $refId,
            $description,
            $metadata
        ): LedgerEntry {
            $lockedWallet = WalletAccount::query()
                ->whereKey($wallet->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lastBalance = (int) LedgerEntry::query()
                ->where('wallet_account_id', $lockedWallet->getKey())
                ->orderByDesc('id')
                ->value('balance_after_cents');

            $newBalance = $type === 'debit'
                ? $lastBalance - $amountCents
                : $lastBalance + $amountCents;

            if ($type === 'debit' && $newBalance < 0) {
                throw new InsufficientBalanceException($amountCents, $lastBalance);
            }

            $entry = LedgerEntry::query()->create([
                'wallet_account_id' => $lockedWallet->getKey(),
                'type' => $type,
                'amount_cents' => $amountCents,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'balance_after_cents' => $newBalance,
                'description' => $description,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            $lockedWallet->forceFill([
                'balance_cents' => $newBalance,
            ])->save();

            return $entry;
        });
    }
}
