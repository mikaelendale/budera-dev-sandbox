<?php

namespace App\Services\SpendControls;

use App\Models\Payment;
use App\Services\SpendControls\Data\PaymentRequestData;
use App\Services\SpendControls\Result\SpendDecision;
use Carbon\Carbon;

class VelocityEngine
{
    public function evaluate(PaymentRequestData $request): SpendDecision
    {
        $wallet = $request->walletAccount;
        $policy = $wallet->policy;
        $sensitivity = $policy?->velocity_sensitivity ?? 'medium';

        $since = Carbon::now()->subHours(24);
        $recentPayments = Payment::query()
            ->where('wallet_account_id', $wallet->getKey())
            ->where('created_at', '>=', $since)
            ->get();

        $txCount24h = $recentPayments->count();
        $txPerHourBaseline = match ($sensitivity) {
            'low' => 50,
            'medium' => 20,
            'high' => 5,
            default => 20,
        };

        $hourThreshold = $txPerHourBaseline * 24;
        if ($txCount24h >= $hourThreshold - 1) {
            $projectedWithRequest = $txCount24h + 1;
            if ($projectedWithRequest > $hourThreshold) {
                return SpendDecision::heldAnomaly();
            }
        }

        if ($policy?->max_new_payees_per_day !== null && $request->payeeRef !== null) {
            $existingPayees = $recentPayments->pluck('payee_ref')->filter()->unique()->values();
            $isNew = ! $existingPayees->contains(fn ($p) => strcasecmp((string) $p, $request->payeeRef) === 0);
            $newCount = $existingPayees->count();
            if ($isNew) {
                $newCount++;
            }
            if ($newCount > (int) $policy->max_new_payees_per_day) {
                return SpendDecision::heldAnomaly();
            }
        }

        $amounts = $recentPayments->pluck('amount_usd')->filter()->map(fn ($v) => (float) $v)->values();
        $requestUsd = $request->amountUsd();
        $meanUsd = $amounts->isEmpty() ? $requestUsd : $amounts->avg();
        $stdUsd = $amounts->isEmpty() ? 0.0 : $this->stdDev($amounts->all(), $meanUsd);
        $deviationThreshold = match ($sensitivity) {
            'low' => 5.0,
            'medium' => 3.0,
            'high' => 2.0,
            default => 3.0,
        };
        if ($stdUsd > 0 && abs($requestUsd - $meanUsd) > $deviationThreshold * $stdUsd) {
            return SpendDecision::heldAnomaly();
        }

        return SpendDecision::approved();
    }

    private function stdDev(array $values, float $mean): float
    {
        if ($values === []) {
            return 0.0;
        }
        $sumSq = array_reduce($values, fn ($carry, $v) => $carry + ($v - $mean) ** 2, 0.0);

        return sqrt($sumSq / count($values));
    }
}
