<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DomainAuditLog;
use App\Services\CompanyWalletFreezeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(): Response
    {
        $companies = Company::query()
            ->with('owner')
            ->withCount('walletAccounts')
            ->withSum('walletAccounts', 'balance_cents')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Company $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'kyb_status' => (string) $c->kyb_status,
                'live_enabled_at' => $c->live_enabled_at?->toIso8601String(),
                'owner_email' => $c->owner?->email,
                'wallet_accounts_count' => (int) $c->wallet_accounts_count,
                'total_balance_cents' => (int) ($c->wallet_accounts_sum_balance_cents ?? 0),
            ]);

        return Inertia::render('admin/companies/index', [
            'companies' => $companies,
        ]);
    }

    public function show(Company $company): Response
    {
        $company->load('owner');

        $members = $company->membersWithRoles()->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'email' => (string) $row->email,
            'role' => (string) $row->role,
        ])->values()->all();

        $wallets = $company->walletAccounts()
            ->with(['user:id,name,email'])
            ->orderByDesc('id')
            ->get()
            ->map(function ($w) {
                $user = $w->user;

                return [
                    'public_id' => $w->public_id,
                    'user_id' => $w->user_id !== null ? (int) $w->user_id : null,
                    'user' => $user !== null ? [
                        'id' => (int) $user->getKey(),
                        'name' => (string) $user->name,
                        'email' => (string) $user->email,
                    ] : null,
                    'status' => (string) $w->status,
                    'environment' => (string) $w->environment,
                    'balance_cents' => (int) $w->balance_cents,
                ];
            })
            ->all();

        $apiKeys = $company->apiKeys()
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($k) => [
                'id' => $k->id,
                'environment' => (string) $k->environment,
                'status' => (string) $k->status,
                'label' => $k->label,
                'abilities' => $k->abilities,
                'created_at' => $k->created_at?->toIso8601String(),
            ])
            ->all();

        $activity = DomainAuditLog::query()
            ->forCompany((int) $company->getKey())
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (DomainAuditLog $row) => [
                'id' => $row->id,
                'action' => $row->action,
                'stream' => $row->stream,
                'actor_type' => $row->actor_type,
                'actor_id' => $row->actor_id,
                'environment' => $row->environment,
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('admin/companies/show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'email' => $company->email,
                'kyb_status' => (string) $company->kyb_status,
                'live_enabled_at' => $company->live_enabled_at?->toIso8601String(),
                'owner_email' => $company->owner?->email,
            ],
            'members' => $members,
            'wallets' => $wallets,
            'apiKeys' => $apiKeys,
            'activity' => $activity,
        ]);
    }

    public function freeze(Request $request, Company $company, CompanyWalletFreezeService $service): RedirectResponse
    {
        $service->freezeAllCompanyWallets($company, $request->user(), $request);

        return redirect()->route('admin.companies.show', $company)
            ->with('status', __('Company wallets frozen.'));
    }

    public function unfreeze(Request $request, Company $company, CompanyWalletFreezeService $service): RedirectResponse
    {
        $service->unfreezeAllCompanyWallets($company, $request->user(), $request);

        return redirect()->route('admin.companies.show', $company)
            ->with('status', __('Company wallets unfrozen.'));
    }
}
