<?php

namespace App\Services\SpendControls;

use App\Services\SpendControls\Data\PaymentRequestData;
use App\Services\SpendControls\Result\SpendDecision;

class SpendControlsPipeline
{
    public function __construct(
        private readonly PolicyGate $policyGate,
        private readonly BalanceGate $balanceGate,
        private readonly VelocityEngine $velocityEngine,
        private readonly ApprovalGate $approvalGate,
        private readonly ComplianceScreen $complianceScreen,
    ) {}

    public function evaluate(PaymentRequestData $request): SpendDecision
    {
        $decision = $this->policyGate->evaluate($request);
        if (! $decision->isApproved()) {
            return $decision;
        }

        $decision = $this->balanceGate->evaluate($request);
        if (! $decision->isApproved()) {
            return $decision;
        }

        $decision = $this->velocityEngine->evaluate($request);
        if (! $decision->isApproved()) {
            return $decision;
        }

        $decision = $this->approvalGate->evaluate($request);
        if (! $decision->isApproved()) {
            return $decision;
        }

        $decision = $this->complianceScreen->evaluate($request);
        if (! $decision->isApproved()) {
            return $decision;
        }

        return SpendDecision::approved();
    }
}
