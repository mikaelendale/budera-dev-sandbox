<?php

namespace App\Services\SpendControls;

use App\Services\SpendControls\Data\PaymentRequestData;
use App\Services\SpendControls\Result\SpendDecision;

class BalanceGate
{
    private const LAYER = 'balance';

    public function evaluate(PaymentRequestData $request): SpendDecision
    {
        $wallet = $request->walletAccount->fresh();
        $balanceCents = (int) $wallet->balance_cents;
        $amountCents = $request->amountCents;

        if ($balanceCents >= $amountCents) {
            return SpendDecision::approved();
        }

        $policy = $wallet->policy;
        $autoTopup = is_array($policy?->auto_topup) ? $policy->auto_topup : null;
        $autoTopupEnabled = ($autoTopup['enabled'] ?? false) === true;

        if ($autoTopupEnabled) {
            return SpendDecision::heldNeedsTopup();
        }

        return SpendDecision::rejected(self::LAYER, 'insufficient_balance');
    }
}
