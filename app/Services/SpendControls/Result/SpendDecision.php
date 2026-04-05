<?php

namespace App\Services\SpendControls\Result;

enum SpendDecisionOutcome: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Held = 'held';
}

readonly class SpendDecision
{
    private function __construct(
        public SpendDecisionOutcome $outcome,
        public ?string $layer = null,
        public ?string $reasonCode = null,
        public ?string $holdType = null,
        public ?int $approvalRequestId = null,
        public ?string $approvalToken = null,
    ) {}

    public static function approved(): self
    {
        return new self(outcome: SpendDecisionOutcome::Approved);
    }

    public static function rejected(string $layer, string $reasonCode): self
    {
        return new self(
            outcome: SpendDecisionOutcome::Rejected,
            layer: $layer,
            reasonCode: $reasonCode,
        );
    }

    public static function heldNeedsTopup(): self
    {
        return new self(
            outcome: SpendDecisionOutcome::Held,
            holdType: 'needs_topup',
        );
    }

    public static function heldAnomaly(): self
    {
        return new self(
            outcome: SpendDecisionOutcome::Held,
            holdType: 'hold_anomaly',
        );
    }

    public static function heldApproval(int $approvalRequestId, string $approvalToken): self
    {
        return new self(
            outcome: SpendDecisionOutcome::Held,
            holdType: 'hold_approval',
            approvalRequestId: $approvalRequestId,
            approvalToken: $approvalToken,
        );
    }

    public function isApproved(): bool
    {
        return $this->outcome === SpendDecisionOutcome::Approved;
    }

    public function isRejected(): bool
    {
        return $this->outcome === SpendDecisionOutcome::Rejected;
    }

    public function isHeld(): bool
    {
        return $this->outcome === SpendDecisionOutcome::Held;
    }
}
