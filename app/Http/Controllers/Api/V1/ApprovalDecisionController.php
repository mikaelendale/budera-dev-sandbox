<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiErrorResponse;
use App\Models\ApiKey;
use App\Models\ApprovalRequest;
use App\Services\SpendControls\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalDecisionController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {}

    public function approve(Request $request, string $token): JsonResponse
    {
        if (! $this->authorizeApproval($request, $token)) {
            return ApiErrorResponse::json('approval_action_forbidden');
        }

        $ok = $this->approvalService->approve($token);

        if (! $ok) {
            return ApiErrorResponse::json(
                'approval_action_failed',
                detail: 'Token may be invalid, expired, or already decided.',
            );
        }

        return response()->json(['status' => 'approved']);
    }

    public function deny(Request $request, string $token): JsonResponse
    {
        if (! $this->authorizeApproval($request, $token)) {
            return ApiErrorResponse::json('approval_action_forbidden');
        }

        $ok = $this->approvalService->deny($token);

        if (! $ok) {
            return ApiErrorResponse::json(
                'approval_action_failed',
                detail: 'Token may be invalid, expired, or already decided.',
            );
        }

        return response()->json(['status' => 'denied']);
    }

    private function authorizeApproval(Request $request, string $token): bool
    {
        $approvalRequest = ApprovalRequest::query()
            ->where('approval_token', $token)
            ->first();

        if ($approvalRequest === null) {
            return false;
        }

        $approvable = $approvalRequest->approvable;
        if ($approvable === null) {
            return false;
        }

        $companyId = null;

        $apiKey = $request->attributes->get('api_key');
        if ($apiKey instanceof ApiKey) {
            $companyId = (int) $apiKey->company_id;
        } else {
            $user = $request->user('api');
            if ($user !== null && method_exists($user, 'firstCompany')) {
                $company = $user->firstCompany();
                $companyId = $company?->getKey();
            }
        }

        if ($companyId === null) {
            return false;
        }

        if ($user = $request->user()) {
            if ($user->is_budera_admin ?? false) {
                return true;
            }
        }

        return $approvable->walletAccount->company_id === $companyId;
    }
}
