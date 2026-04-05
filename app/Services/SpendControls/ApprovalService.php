<?php

namespace App\Services\SpendControls;

use App\Models\ApprovalRequest;
use App\Models\Payment;
use App\Services\Audit\TransitionRecorder;
use App\Services\PaymentService;
use App\States\Payment\PaymentApproved;
use App\States\Payment\PaymentFailed;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function __construct(
        private readonly TransitionRecorder $transitionRecorder,
        private readonly PaymentService $paymentService,
    ) {}

    public function approve(string $token): bool
    {
        return $this->decide($token, true);
    }

    public function deny(string $token): bool
    {
        return $this->decide($token, false);
    }

    private function decide(string $token, bool $approve): bool
    {
        $request = ApprovalRequest::query()
            ->where('approval_token', $token)
            ->where('status', 'pending')
            ->first();

        if ($request === null) {
            return false;
        }

        if ($request->expires_at->isPast()) {
            $request->update(['status' => 'expired']);

            return false;
        }

        $resumePaymentId = null;

        DB::transaction(function () use ($request, $approve, &$resumePaymentId): void {
            $request->update([
                'status' => $approve ? 'approved' : 'denied',
                'decided_at' => now(),
            ]);

            $approvable = $request->approvable;
            if ($approvable instanceof Payment) {
                $from = $approvable->status->getValue();
                $targetState = $approve ? PaymentApproved::class : PaymentFailed::class;
                if ($approvable->status->canTransitionTo($targetState)) {
                    $approvable->status->transitionTo($targetState);
                    if (! $approve) {
                        $approvable->held_reason = 'approval_denied';
                    }
                    $approvable->save();

                    $fresh = $approvable->fresh();
                    $wallet = $fresh?->walletAccount()->first();
                    if ($fresh !== null && $wallet !== null) {
                        $to = $fresh->status->getValue();
                        $this->transitionRecorder->record(
                            $fresh,
                            $from,
                            $to,
                            [
                                'stream' => 'developer',
                                'actor_type' => 'end_user',
                                'actor_id' => null,
                                'action' => $approve ? 'payment.approval_token_approved' : 'payment.approval_token_denied',
                                'resource_type' => 'payments',
                                'resource_id' => (string) $fresh->getKey(),
                                'environment' => $fresh->environment,
                                'account_id' => (int) $wallet->getKey(),
                                'metadata' => [
                                    'company_id' => (string) $wallet->company_id,
                                    'wallet_account_id' => (string) $wallet->getKey(),
                                    'approval_request_id' => (string) $request->getKey(),
                                ],
                            ],
                        );
                    }

                    if ($approve && $fresh instanceof Payment && $fresh->status instanceof PaymentApproved) {
                        $resumePaymentId = $fresh->getKey();
                    }
                }
            }
        });

        if ($resumePaymentId !== null) {
            $payment = Payment::query()->find($resumePaymentId);
            $wallet = $payment?->walletAccount()->first();
            if ($payment instanceof Payment && $wallet !== null && $payment->status instanceof PaymentApproved) {
                $partnerAccountId = (string) $wallet->partner_account_id;
                $amountCents = (int) round(((float) $payment->amount_usd) * 100);
                $this->paymentService->submitApprovedPaymentToAch($payment, $wallet, $partnerAccountId, $amountCents);
            }
        }

        return true;
    }
}
