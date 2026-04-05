<?php

namespace App\Http\Controllers\BankPartner;

use App\Http\Controllers\Controller;
use App\Models\WalletAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function index(Request $request, LedgerService $ledger): Response
    {
        $wallets = WalletAccount::query()
            ->withoutGlobalScopes()
            ->where('status', 'active')
            ->orderByDesc('balance_cents')
            ->paginate(50)
            ->through(function ($wallet) use ($ledger) {
                $ledgerBalance = $ledger->balanceForAccount($wallet);
                $cachedBalance = (int) $wallet->balance_cents;

                return [
                    'public_id' => $wallet->public_id,
                    'company_id' => $wallet->company_id,
                    'cached_balance_cents' => $cachedBalance,
                    'ledger_balance_cents' => $ledgerBalance,
                    'mismatch' => $cachedBalance !== $ledgerBalance,
                    'environment' => $wallet->environment,
                ];
            });

        return Inertia::render('bank-partner/reconciliation', [
            'wallets' => $wallets,
        ]);
    }
}
