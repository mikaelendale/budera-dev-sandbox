<?php

namespace App\Services\SpendControls;

use App\Models\Payment;
use App\Services\SpendControls\Data\PaymentRequestData;
use App\Services\SpendControls\Result\SpendDecision;
use Carbon\Carbon;

class PolicyGate
{
    private const LAYER = 'policy';

    public function evaluate(PaymentRequestData $request): SpendDecision
    {
        $wallet = $request->walletAccount;
        $policy = $wallet->policy;

        if ($policy === null) {
            return SpendDecision::approved();
        }

        $amountUsd = $request->amountUsd();

        if ($policy->per_tx_limit_usd !== null && $amountUsd > (float) $policy->per_tx_limit_usd) {
            return SpendDecision::rejected(self::LAYER, 'per_tx_limit_exceeded');
        }

        $todayStart = Carbon::today($wallet->created_at?->timezone ?? 'UTC');

        if ($policy->daily_tx_count !== null) {
            $todayCount = Payment::query()
                ->where('wallet_account_id', $wallet->getKey())
                ->where('created_at', '>=', $todayStart)
                ->count();

            if ($todayCount >= (int) $policy->daily_tx_count) {
                return SpendDecision::rejected(self::LAYER, 'daily_tx_count_exceeded');
            }
        }

        if ($policy->daily_spend_limit_usd !== null) {
            $todaySpendUsd = (float) Payment::query()
                ->where('wallet_account_id', $wallet->getKey())
                ->where('created_at', '>=', $todayStart)
                ->sum('amount_usd');

            if ($todaySpendUsd + $amountUsd > (float) $policy->daily_spend_limit_usd) {
                return SpendDecision::rejected(self::LAYER, 'daily_spend_limit_exceeded');
            }
        }

        $allowedCategories = $policy->allowed_categories;
        if (is_array($allowedCategories) && $allowedCategories !== [] && $request->category !== null) {
            if (! in_array($request->category, $allowedCategories, true)) {
                return SpendDecision::rejected(self::LAYER, 'category_not_allowed');
            }
        }

        $blockedPayees = $policy->blocked_payees;
        if (is_array($blockedPayees) && $blockedPayees !== [] && $request->payeeRef !== null) {
            foreach ($blockedPayees as $blocked) {
                if (stripos($request->payeeRef, (string) $blocked) !== false ||
                    stripos((string) $blocked, $request->payeeRef) !== false) {
                    return SpendDecision::rejected(self::LAYER, 'payee_blocked');
                }
            }
        }

        if ($policy->business_hours_only) {
            $now = Carbon::now($wallet->created_at?->timezone ?? 'UTC');
            $hour = (int) $now->format('H');
            $dow = (int) $now->format('w');
            if ($dow === 0 || $dow === 6 || $hour < 9 || $hour >= 17) {
                return SpendDecision::rejected(self::LAYER, 'outside_business_hours');
            }
        }

        if ($policy->max_new_payees_per_day !== null && $request->payeeRef !== null) {
            $existingPayeesToday = Payment::query()
                ->where('wallet_account_id', $wallet->getKey())
                ->where('created_at', '>=', $todayStart)
                ->whereNotNull('payee_ref')
                ->distinct()
                ->pluck('payee_ref')
                ->filter()
                ->values();

            $isNewPayee = ! $existingPayeesToday->contains(fn ($p) => strcasecmp((string) $p, $request->payeeRef) === 0);
            $newPayeesCount = $existingPayeesToday->count();
            if ($isNewPayee) {
                $newPayeesCount++;
            }
            if ($newPayeesCount > (int) $policy->max_new_payees_per_day) {
                return SpendDecision::rejected(self::LAYER, 'max_new_payees_exceeded');
            }
        }

        return SpendDecision::approved();
    }
}
