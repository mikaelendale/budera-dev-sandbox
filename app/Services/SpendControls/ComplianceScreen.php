<?php

namespace App\Services\SpendControls;

use App\Models\ComplianceFlag;
use App\Models\Payment;
use App\Services\SpendControls\Data\PaymentRequestData;
use App\Services\SpendControls\Result\SpendDecision;
use Carbon\Carbon;

class ComplianceScreen
{
    private const LAYER = 'compliance';

    private const OFAC_BLOCKED_PATTERNS = ['ofac_blocked', 'sanctions', 'sdn_list'];

    private const HIGH_RISK_PAYEE_PATTERNS = ['high_risk', 'embargo'];

    public function evaluate(PaymentRequestData $request): SpendDecision
    {
        $payment = $request->payment;

        if (! $payment instanceof Payment) {
            return SpendDecision::approved();
        }

        if ($this->checkOfac($payment)) {
            return SpendDecision::heldAnomaly();
        }

        if ($this->checkHighRiskPayee($payment)) {
            return SpendDecision::heldAnomaly();
        }

        if ($this->checkStructuringHold($payment)) {
            return SpendDecision::heldAnomaly();
        }

        return SpendDecision::approved();
    }

    public function run(Payment $payment): void
    {
        $this->checkOfac($payment);
        $this->checkHighRiskPayee($payment);
        $this->checkStructuringHold($payment);
    }

    private function checkOfac(Payment $payment): bool
    {
        $payeeRef = (string) ($payment->payee_ref ?? '');
        $metadata = $payment->metadata ?? [];
        $payeeName = $metadata['payee_name'] ?? $payeeRef;

        foreach (self::OFAC_BLOCKED_PATTERNS as $pattern) {
            if (stripos($payeeName, $pattern) !== false || stripos($payeeRef, $pattern) !== false) {
                ComplianceFlag::query()->create([
                    'flaggable_type' => Payment::class,
                    'flaggable_id' => $payment->getKey(),
                    'flag_type' => 'ofac',
                    'severity' => 'critical',
                    'details' => [
                        'reason' => 'ofac_blocked',
                        'matched' => $pattern,
                        'layer' => self::LAYER,
                    ],
                ]);

                return true;
            }
        }

        return false;
    }

    private function checkHighRiskPayee(Payment $payment): bool
    {
        $payeeRef = (string) ($payment->payee_ref ?? '');

        foreach (self::HIGH_RISK_PAYEE_PATTERNS as $pattern) {
            if (stripos($payeeRef, $pattern) !== false) {
                ComplianceFlag::query()->create([
                    'flaggable_type' => Payment::class,
                    'flaggable_id' => $payment->getKey(),
                    'flag_type' => 'high_risk_payee',
                    'severity' => 'high',
                    'details' => [
                        'reason' => 'high_risk_payee',
                        'matched' => $pattern,
                        'layer' => self::LAYER,
                    ],
                ]);

                return true;
            }
        }

        return false;
    }

    private function checkStructuringHold(Payment $payment): bool
    {
        $since = Carbon::now()->subHours(1);
        $walletId = $payment->wallet_account_id;
        $amountUsd = (float) $payment->amount_usd;

        $similarPayments = Payment::query()
            ->where('wallet_account_id', $walletId)
            ->where('id', '!=', $payment->getKey())
            ->where('created_at', '>=', $since)
            ->get();

        $withinPct = 0.05;
        $similarCount = $similarPayments->filter(function (Payment $p) use ($amountUsd, $withinPct) {
            $other = (float) $p->amount_usd;

            return abs($other - $amountUsd) / max($amountUsd, 0.01) <= $withinPct;
        })->count();

        if ($similarCount >= 2) {
            ComplianceFlag::query()->create([
                'flaggable_type' => Payment::class,
                'flaggable_id' => $payment->getKey(),
                'flag_type' => 'structuring',
                'severity' => 'high',
                'details' => [
                    'reason' => 'structuring',
                    'similar_count' => $similarCount + 1,
                    'amount_usd' => $amountUsd,
                    'layer' => self::LAYER,
                ],
            ]);

            return true;
        }

        return false;
    }
}
