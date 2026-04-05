<?php

namespace App\Console\Commands;

use App\Models\WalletAccount;
use App\Scopes\CompanyScope;
use App\Services\Ledger\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreditWalletCommand extends Command
{
    protected $signature = 'budera:credit-wallet
                            {public_id : Wallet public_id (e.g. act_...)}
                            {amount_cents=10000 : Credit amount in whole cents (default 10000 = \$100.00)}';

    protected $description = 'Sandbox/local only: post a ledger credit so the wallet balance increases (for API testing without a full ACH top-up flow)';

    public function handle(LedgerService $ledger): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Refusing to run outside local/testing. This command is for development only.');

            return self::FAILURE;
        }

        $publicId = (string) $this->argument('public_id');
        $amountCents = (int) $this->argument('amount_cents');

        if ($amountCents <= 0) {
            $this->error('amount_cents must be a positive integer.');

            return self::FAILURE;
        }

        $wallet = WalletAccount::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('public_id', $publicId)
            ->first();

        if ($wallet === null) {
            $this->error("No wallet found with public_id [{$publicId}].");

            return self::FAILURE;
        }

        $refId = 'dev_credit_'.Str::uuid()->toString();

        $entry = $ledger->credit(
            $wallet,
            $amountCents,
            'sandbox.dev_credit',
            $refId,
            'Manual sandbox credit (budera:credit-wallet)',
        );

        $wallet->refresh();

        $this->info("Credited {$amountCents} cents. Ledger entry id: {$entry->getKey()}.");
        $this->line("New balance_cents: {$wallet->balance_cents}");

        return self::SUCCESS;
    }
}
