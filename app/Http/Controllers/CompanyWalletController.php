<?php

namespace App\Http\Controllers;

use App\Models\StateTransition;
use App\Models\WalletAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyWalletController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAnyAsCompanyMember', WalletAccount::class);

        $wallets = WalletAccount::query()
            ->forEnvironment()
            ->with('policy')
            ->orderByDesc('id')
            ->get()
            ->map(fn (WalletAccount $w) => [
                'public_id' => $w->public_id,
                'status' => (string) $w->status,
                'balance_cents' => (int) $w->balance_cents,
                'environment' => (string) $w->environment,
                'agent_id' => $w->agent_id,
                'has_policy' => $w->policy !== null,
            ])
            ->values()
            ->all();

        return Inertia::render('company/wallets/index', [
            'wallets' => $wallets,
        ]);
    }

    public function show(Request $request, WalletAccount $walletAccount): Response
    {
        $this->authorize('viewAsCompanyMember', $walletAccount);

        $walletAccount->load([
            'policy',
            'kycVerifications' => fn ($q) => $q->latest('id')->limit(5),
        ]);

        $ledgerPage = $walletAccount->ledgerEntries()
            ->orderByDesc('id')
            ->simplePaginate(20)
            ->withQueryString();

        $transitionsPage = StateTransition::query()
            ->where('model_type', $walletAccount->getMorphClass())
            ->where('model_id', (string) $walletAccount->getKey())
            ->orderByDesc('id')
            ->simplePaginate(20)
            ->withQueryString();

        $latestKyc = $walletAccount->kycVerifications->first();

        $ledgerRows = collect($ledgerPage->items())->map(fn ($entry) => [
            'id' => $entry->id,
            'type' => $entry->type,
            'amount_cents' => (int) $entry->amount_cents,
            'balance_after_cents' => (int) $entry->balance_after_cents,
            'description' => $entry->description,
            'created_at' => $entry->created_at?->toIso8601String(),
        ])->values()->all();

        $transitionRows = collect($transitionsPage->items())->map(fn ($row) => [
            'id' => $row->id,
            'from_state' => $row->from_state,
            'to_state' => $row->to_state,
            'actor_type' => $row->actor_type,
            'actor_id' => $row->actor_id,
            'created_at' => $row->created_at?->toIso8601String(),
        ])->values()->all();

        return Inertia::render('company/wallets/show', [
            'wallet' => [
                'public_id' => $walletAccount->public_id,
                'status' => (string) $walletAccount->status,
                'balance_cents' => (int) $walletAccount->balance_cents,
                'environment' => (string) $walletAccount->environment,
                'agent_id' => $walletAccount->agent_id,
                'metadata' => $walletAccount->metadata,
            ],
            'policy' => $walletAccount->policy === null ? null : [
                'per_tx_limit_usd' => $walletAccount->policy->per_tx_limit_usd,
                'daily_spend_limit_usd' => $walletAccount->policy->daily_spend_limit_usd,
                'velocity_sensitivity' => $walletAccount->policy->velocity_sensitivity,
            ],
            'latestKyc' => $latestKyc === null ? null : [
                'status' => (string) $latestKyc->status,
                'updated_at' => $latestKyc->updated_at?->toIso8601String(),
            ],
            'canManagePolicy' => $request->user()?->can('updatePolicy', $walletAccount) ?? false,
            'ledgerEntries' => $ledgerRows,
            'ledgerPagination' => [
                'next_url' => $ledgerPage->nextPageUrl(),
                'prev_url' => $ledgerPage->previousPageUrl(),
            ],
            'stateTransitions' => $transitionRows,
            'stateTransitionsPagination' => [
                'next_url' => $transitionsPage->nextPageUrl(),
                'prev_url' => $transitionsPage->previousPageUrl(),
            ],
        ]);
    }
}
