<?php

namespace App\Http\Controllers;

use App\Models\CompanyInvitation;
use App\Models\KybReview;
use App\Models\WalletAccount;
use App\States\KybReview\KybReviewPending;
use App\States\KybReview\KybReviewUnderReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $company = $user?->firstCompany();

        if ($company === null) {
            return redirect()->route('dashboard');
        }

        $canManageInvites = $user->canManageCompanyInvites($company);

        $members = $company->membersWithRoles()->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'email' => (string) $row->email,
            'role' => (string) $row->role,
        ])->values()->all();

        $pendingInvitations = CompanyInvitation::query()
            ->where('company_id', $company->id)
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CompanyInvitation $invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'created_at' => $invitation->created_at?->toIso8601String(),
                'is_expired' => $invitation->isExpired(),
            ])
            ->values()
            ->all();

        $hasOpenKyb = KybReview::query()
            ->where('company_id', $company->id)
            ->where(function ($q): void {
                $q->where('status', KybReviewPending::class)
                    ->orWhere('status', KybReviewUnderReview::class);
            })
            ->exists();

        $canSubmitKybReview = (int) $company->owner_id === (int) $user->id
            && $company->live_enabled_at === null
            && ! $hasOpenKyb
            && (string) $company->kyb_status !== 'approved';

        $canRequestLiveAccess = (int) $company->owner_id === (int) $user->id
            && (string) $company->kyb_status === 'approved'
            && $company->live_enabled_at === null;

        $isCompanyOwner = (int) $company->owner_id === (int) $user->id;

        $canManageWallets = $user->hasCompanyPermission($company, 'company.wallets.manage');

        $spendPolicyWallet = null;
        if ($canManageWallets) {
            $wallet = WalletAccount::query()
                ->where('company_id', $company->id)
                ->forEnvironment()
                ->orderBy('id')
                ->first();

            if ($wallet !== null) {
                $policy = $wallet->policy;
                $spendPolicyWallet = [
                    'id' => $wallet->getKey(),
                    'public_id' => $wallet->public_id,
                    'environment' => $wallet->environment,
                    'policy' => $policy === null ? null : [
                        'per_tx_limit_usd' => $policy->per_tx_limit_usd,
                        'daily_spend_limit_usd' => $policy->daily_spend_limit_usd,
                        'daily_tx_count' => $policy->daily_tx_count,
                        'require_approval_above' => $policy->require_approval_above,
                        'approval_timeout_secs' => $policy->approval_timeout_secs,
                        'max_new_payees_per_day' => $policy->max_new_payees_per_day,
                        'business_hours_only' => (bool) $policy->business_hours_only,
                        'velocity_sensitivity' => $policy->velocity_sensitivity,
                    ],
                ];
            }
        }

        return Inertia::render('company/settings', [
            'canManageInvites' => $canManageInvites,
            'canManageWebhooks' => $user->hasCompanyPermission($company, 'company.webhooks.manage'),
            'canManageWallets' => $canManageWallets,
            'spendPolicyWallet' => $spendPolicyWallet,
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'canSubmitKybReview' => $canSubmitKybReview,
            'canRequestLiveAccess' => $canRequestLiveAccess,
            'isCompanyOwner' => $isCompanyOwner,
            'kybStatus' => (string) $company->kyb_status,
            'liveEnabledAt' => $company->live_enabled_at?->toIso8601String(),
        ]);
    }
}
