<?php

namespace App\Http\Controllers\BankPartner;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\WalletAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $totalAccounts = WalletAccount::query()->withoutGlobalScopes()->count();
        $totalBalanceCents = (int) WalletAccount::query()->withoutGlobalScopes()->sum('balance_cents');
        $totalCompanies = Company::query()->count();
        $activeAccounts = WalletAccount::query()->withoutGlobalScopes()->where('status', 'active')->count();

        return Inertia::render('bank-partner/dashboard', [
            'stats' => [
                'total_accounts' => $totalAccounts,
                'active_accounts' => $activeAccounts,
                'total_balance_cents' => $totalBalanceCents,
                'total_companies' => $totalCompanies,
            ],
        ]);
    }
}
