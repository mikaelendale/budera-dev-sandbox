<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWalletPolicyRequest;
use App\Models\Policy;
use App\Models\WalletAccount;
use App\Services\Audit\AuditService;
use App\Services\Audit\CorrelationId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyWalletPolicyController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function show(Request $request, WalletAccount $walletAccount): Response
    {
        $this->authorize('viewAsCompanyMember', $walletAccount);

        $policy = $walletAccount->policy;

        return Inertia::render('company/wallets/policy', [
            'wallet' => [
                'public_id' => $walletAccount->public_id,
                'environment' => (string) $walletAccount->environment,
                'status' => (string) $walletAccount->status,
            ],
            'canManagePolicy' => $request->user()?->can('updatePolicy', $walletAccount) ?? false,
            'policy' => $policy === null ? null : [
                'agent_type' => $policy->agent_type,
                'per_tx_limit_usd' => $policy->per_tx_limit_usd,
                'daily_spend_limit_usd' => $policy->daily_spend_limit_usd,
                'daily_tx_count' => $policy->daily_tx_count,
                'allowed_categories' => $policy->allowed_categories,
                'blocked_payees' => $policy->blocked_payees,
                'require_approval_above' => $policy->require_approval_above,
                'approval_timeout_secs' => $policy->approval_timeout_secs,
                'max_new_payees_per_day' => $policy->max_new_payees_per_day,
                'business_hours_only' => (bool) $policy->business_hours_only,
                'velocity_sensitivity' => $policy->velocity_sensitivity,
                'auto_topup' => $policy->auto_topup,
            ],
        ]);
    }

    public function update(UpdateWalletPolicyRequest $request, WalletAccount $walletAccount): RedirectResponse
    {
        $this->authorize('updatePolicy', $walletAccount);

        $validated = $request->validated();

        $policy = Policy::query()->firstOrCreate(
            ['wallet_account_id' => $walletAccount->getKey()],
            [
                'business_hours_only' => false,
                'velocity_sensitivity' => 'medium',
            ],
        );

        $wasNew = $policy->wasRecentlyCreated;

        $policy->fill($validated);
        $policy->save();

        $this->auditService->recordDomainAudit([
            'stream' => 'developer',
            'actor_type' => 'user',
            'actor_id' => (string) $request->user()->getKey(),
            'action' => $wasNew ? 'wallet_spend.policy_created' : 'wallet_spend.policy_updated',
            'resource_type' => 'policies',
            'resource_id' => (string) $policy->getKey(),
            'environment' => $walletAccount->environment,
            'metadata' => [
                'company_id' => (string) $walletAccount->company_id,
                'wallet_account_id' => (string) $walletAccount->getKey(),
            ],
            'correlation_id' => CorrelationId::current($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('company.wallets.policy.show', $walletAccount)
            ->with('status', __('Spend policy saved.'));
    }
}
