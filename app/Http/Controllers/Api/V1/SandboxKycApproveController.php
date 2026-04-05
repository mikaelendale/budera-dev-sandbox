<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiErrorResponse;
use App\Models\User;
use App\Models\WalletKycVerification;
use App\Services\Banking\WalletProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SandboxKycApproveController extends Controller
{
    public function __invoke(
        Request $request,
        WalletKycVerification $walletKycVerification,
        WalletProvisioningService $walletProvisioning,
    ): JsonResponse {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiErrorResponse::json('unauthenticated_api');
        }

        $company = $user->firstCompany();
        if ($company === null) {
            return ApiErrorResponse::json('company_required');
        }

        $wallet = $walletKycVerification->walletAccount;
        if ((int) $wallet->company_id !== (int) $company->getKey()) {
            return ApiErrorResponse::json('forbidden');
        }

        $walletProvisioning->forceApproveKycForSandbox($walletKycVerification);

        $walletKycVerification->refresh();
        $wallet->refresh();

        return response()->json([
            'status' => $walletKycVerification->status->getValue(),
            'wallet_account_id' => $wallet->public_id,
            'wallet_status' => $wallet->status->getValue(),
        ]);
    }
}
