<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiErrorResponse;
use App\Models\WalletAccount;
use App\Services\Banking\WalletProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletAccountController extends Controller
{
    public function __construct(
        private readonly WalletProvisioningService $provisioning,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->firstCompany();
        if ($company === null) {
            return ApiErrorResponse::json('company_required');
        }

        $account = $this->provisioning->provision($company, $user);

        return response()->json([
            'id' => $account->public_id,
            'environment' => $account->environment,
            'status' => $account->status->getValue(),
        ], 201);
    }

    public function show(Request $request, WalletAccount $walletAccount): JsonResponse
    {
        $deny = $this->walletAuthorizationFailure($request, $walletAccount);
        if ($deny !== null) {
            return $deny;
        }

        $walletAccount->load(['policy', 'bankLinks' => fn ($q) => $q->latest('id')->limit(1)]);

        $policy = $walletAccount->policy;
        $bankLink = $walletAccount->bankLinks->first();

        return response()->json([
            'id' => $walletAccount->public_id,
            'environment' => $walletAccount->environment,
            'status' => $walletAccount->status->getValue(),
            'balance_usd' => $walletAccount->balanceUsd(),
            'agent_id' => $walletAccount->agent_id,
            'policy' => $policy === null ? null : [
                'per_tx_limit_usd' => $policy->per_tx_limit_usd !== null ? (string) $policy->per_tx_limit_usd : null,
                'daily_spend_limit_usd' => $policy->daily_spend_limit_usd !== null ? (string) $policy->daily_spend_limit_usd : null,
                'daily_tx_count' => $policy->daily_tx_count,
                'require_approval_above' => $policy->require_approval_above !== null ? (string) $policy->require_approval_above : null,
                'business_hours_only' => (bool) $policy->business_hours_only,
                'velocity_sensitivity' => $policy->velocity_sensitivity,
            ],
            'bank_link' => $bankLink === null ? null : [
                'id' => $bankLink->public_id,
                'status' => $bankLink->status->getValue(),
                'account_last4' => $bankLink->account_last4,
            ],
        ]);
    }

    public function submitKyc(Request $request, WalletAccount $walletAccount): JsonResponse
    {
        $deny = $this->walletAuthorizationFailure($request, $walletAccount);
        if ($deny !== null) {
            return $deny;
        }

        $validated = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'string', 'max:32'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'last4_ssn' => ['nullable', 'string', 'max:4'],
        ]);

        $kyc = $this->provisioning->submitKyc($walletAccount, $validated);

        return response()->json([
            'wallet_account_id' => $walletAccount->public_id,
            'status' => $kyc->status,
        ], 201);
    }

    private function walletAuthorizationFailure(Request $request, WalletAccount $walletAccount): ?JsonResponse
    {
        $user = $request->user();
        $company = $user->firstCompany();
        if ($company === null || (int) $walletAccount->company_id !== (int) $company->getKey()) {
            return ApiErrorResponse::json('forbidden');
        }

        return null;
    }
}
