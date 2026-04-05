<?php

namespace App\Console\Commands;

use App\Models\WalletAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ledger:reconcile')]
#[Description('Reconcile wallet balance cache against ledger entries')]
class LedgerReconcileCommand extends Command
{
    public function handle(LedgerService $ledgerService): int
    {
        $mismatches = [];

        WalletAccount::query()
            ->withoutCompanyScope()
            ->orderBy('id')
            ->chunkById(250, function ($wallets) use (&$mismatches, $ledgerService): void {
                foreach ($wallets as $wallet) {
                    /** @var WalletAccount $wallet */
                    $ledgerBalance = $ledgerService->balanceForAccount($wallet);
                    $cachedBalance = (int) $wallet->balance_cents;

                    if ($ledgerBalance !== $cachedBalance) {
                        $mismatches[] = [
                            'wallet_id' => (int) $wallet->id,
                            'wallet_public_id' => (string) $wallet->public_id,
                            'cached_balance_cents' => $cachedBalance,
                            'ledger_balance_cents' => $ledgerBalance,
                            'difference_cents' => $ledgerBalance - $cachedBalance,
                        ];
                    }
                }
            });

        if ($mismatches === []) {
            $this->info('Ledger reconciliation complete: no mismatches found.');

            return self::SUCCESS;
        }

        $this->error('Ledger reconciliation mismatches found: '.count($mismatches));
        $this->table(
            ['wallet_id', 'wallet_public_id', 'cached_balance_cents', 'ledger_balance_cents', 'difference_cents'],
            $mismatches
        );

        return self::FAILURE;
    }
}
