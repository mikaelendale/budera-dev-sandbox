<?php

namespace App\Services\SpendControls;

use App\Models\ApprovalRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\SpendControls\Data\PaymentRequestData;
use App\Services\SpendControls\Result\SpendDecision;
use Illuminate\Support\Str;

class ApprovalGate
{
    public function evaluate(PaymentRequestData $request): SpendDecision
    {
        $wallet = $request->walletAccount;
        $policy = $wallet->policy;

        if ($policy === null || $policy->require_approval_above === null) {
            return SpendDecision::approved();
        }

        $amountUsd = $request->amountUsd();
        if ($amountUsd <= (float) $policy->require_approval_above) {
            return SpendDecision::approved();
        }

        $payment = $request->payment;
        if ($payment === null) {
            return SpendDecision::approved();
        }

        $approvalRequest = ApprovalRequest::query()->create([
            'approvable_type' => Payment::class,
            'approvable_id' => $payment->getKey(),
            'requested_by_type' => $request->requestedByType ?? User::class,
            'requested_by_id' => $request->requestedById ?? $wallet->user_id,
            'approval_token' => Str::random(64),
            'expires_at' => now()->addSeconds((int) ($policy->approval_timeout_secs ?? 3600)),
            'status' => 'pending',
        ]);

        return SpendDecision::heldApproval(
            approvalRequestId: (int) $approvalRequest->id,
            approvalToken: $approvalRequest->approval_token,
        );
    }
}
