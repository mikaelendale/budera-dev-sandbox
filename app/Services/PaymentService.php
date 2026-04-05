<?php

namespace App\Services;

use App\Contracts\Banking\ColumnBankService;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\WalletAccount;
use App\Notifications\Transactional\LowBalanceNotification;
use App\Notifications\Transactional\PaymentHeldForApprovalNotification;
use App\Services\Audit\TransitionRecorder;
use App\Services\Ledger\InsufficientBalanceException;
use App\Services\Ledger\LedgerService;
use App\Services\Mail\TransactionalMail;
use App\Services\SpendControls\Data\PaymentRequestData;
use App\Services\SpendControls\Result\SpendDecision;
use App\Services\SpendControls\SpendControlsPipeline;
use App\Services\Webhooks\DeveloperWebhookContext;
use App\States\Payment\PaymentApproved;
use App\States\Payment\PaymentFailed;
use App\States\Payment\PaymentHeldAnomaly;
use App\States\Payment\PaymentHeldApproval;
use App\States\Payment\PaymentPending;
use App\States\Payment\PaymentProcessing;
use App\States\WalletAccount\WalletAccountActive;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Outbound ACH initiation is synchronous. When adding async payment-rail work (e.g. settlement retries),
 * dispatch jobs with onQueue(config('budera.queues.payments')).
 */
class PaymentService
{
    public function __construct(
        private readonly SpendControlsPipeline $spendControlsPipeline,
        private readonly ColumnBankService $columnBank,
        private readonly TransitionRecorder $transitionRecorder,
        private readonly LedgerService $ledgerService,
    ) {}

    public function createOutboundAchPayment(
        WalletAccount $wallet,
        int $amountCents,
        ?string $payeeRef,
        ?string $category,
        ?string $idempotencyKey,
    ): Payment {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('amount_cents must be positive.');
        }

        if (! $wallet->status instanceof WalletAccountActive) {
            throw new InvalidArgumentException('wallet_not_active');
        }

        $partnerAccountId = (string) $wallet->partner_account_id;
        if ($partnerAccountId === '') {
            throw new RuntimeException('wallet_missing_partner_account_id');
        }

        $amountUsd = round($amountCents / 100, 2);

        return DB::transaction(function () use (
            $wallet,
            $amountCents,
            $amountUsd,
            $payeeRef,
            $category,
            $idempotencyKey,
            $partnerAccountId
        ): Payment {
            $payment = Payment::query()->create([
                'wallet_account_id' => $wallet->getKey(),
                'environment' => $wallet->environment,
                'status' => PaymentPending::class,
                'direction' => 'outbound',
                'rail' => 'ach',
                'payee_ref' => $payeeRef,
                'idempotency_key' => $idempotencyKey,
                'amount_usd' => $amountUsd,
                'metadata' => array_filter([
                    'category' => $category,
                ]),
                'held_reason' => null,
            ]);

            $requestData = new PaymentRequestData(
                walletAccount: $wallet->fresh(),
                amountCents: $amountCents,
                category: $category,
                payeeRef: $payeeRef,
                requestedByType: null,
                requestedById: $wallet->user_id !== null ? (string) $wallet->user_id : null,
                payment: $payment,
            );

            $decision = $this->spendControlsPipeline->evaluate($requestData);

            return $this->applySpendDecision($payment, $decision, $partnerAccountId, $amountCents, $wallet);
        });
    }

    private function applySpendDecision(
        Payment $payment,
        SpendDecision $decision,
        string $partnerAccountId,
        int $amountCents,
        WalletAccount $wallet,
    ): Payment {
        if ($decision->isRejected()) {
            $from = $payment->status->getValue();
            $payment->status->transitionTo(PaymentFailed::class);
            $payment->held_reason = ($decision->layer ?? 'policy').':'.($decision->reasonCode ?? 'rejected');
            $payment->save();
            $fresh = $payment->fresh();
            $this->recordPaymentTransition($fresh, $from, 'failed', 'payment.rejected_spend_controls', $wallet, [
                'reason_layer' => $decision->layer,
                'reason_code' => $decision->reasonCode,
            ], $fresh !== null
                ? DeveloperWebhookContext::forPayment('payment.failed', $fresh, $wallet, [
                    'failure_kind' => 'spend_controls_rejected',
                    'reason_layer' => $decision->layer,
                    'reason_code' => $decision->reasonCode,
                ])
                : null);

            return $payment->fresh();
        }

        if ($decision->isHeld()) {
            return $this->applyHoldDecision($payment, $decision, $wallet, $amountCents);
        }

        if (! $decision->isApproved()) {
            $from = $payment->status->getValue();
            $payment->status->transitionTo(PaymentFailed::class);
            $payment->held_reason = 'unknown_decision';
            $payment->save();
            $freshU = $payment->fresh();
            $this->recordPaymentTransition($freshU, $from, 'failed', 'payment.unknown_decision', $wallet, [], $freshU !== null
                ? DeveloperWebhookContext::forPayment('payment.failed', $freshU, $wallet, ['failure_kind' => 'unknown_decision'])
                : null);

            return $payment->fresh();
        }

        $fromApproved = $payment->status->getValue();
        $payment->status->transitionTo(PaymentApproved::class);
        $payment->save();
        $approvedAfterTransition = $payment->fresh();
        $this->recordPaymentTransition($approvedAfterTransition, $fromApproved, 'approved', 'payment.approved', $wallet, [], $approvedAfterTransition !== null
            ? DeveloperWebhookContext::forPayment('payment.approved', $approvedAfterTransition, $wallet)
            : null);

        $approved = $payment->fresh();
        if ($approved === null) {
            return $payment;
        }

        return $this->submitApprovedPaymentToAch($approved, $wallet, $partnerAccountId, $amountCents);
    }

    public function submitApprovedPaymentToAch(
        Payment $payment,
        WalletAccount $wallet,
        string $partnerAccountId,
        int $amountCents,
    ): Payment {
        if (! $payment->status instanceof PaymentApproved) {
            return $payment;
        }

        if ($partnerAccountId === '') {
            $from = $payment->status->getValue();
            $payment->status->transitionTo(PaymentFailed::class);
            $payment->held_reason = 'wallet_missing_partner_account_id';
            $payment->save();
            $fresh = $payment->fresh();
            $this->recordPaymentTransition($fresh, $from, 'failed', 'payment.wallet_not_ready', $wallet, [], $fresh !== null
                ? DeveloperWebhookContext::forPayment('payment.failed', $fresh, $wallet, ['failure_kind' => 'wallet_missing_partner_account_id'])
                : null);

            return $payment->fresh();
        }

        if ($amountCents <= 0) {
            $from = $payment->status->getValue();
            $payment->status->transitionTo(PaymentFailed::class);
            $payment->held_reason = 'invalid_amount';
            $payment->save();
            $fresh = $payment->fresh();
            $this->recordPaymentTransition($fresh, $from, 'failed', 'payment.invalid_amount', $wallet, [], $fresh !== null
                ? DeveloperWebhookContext::forPayment('payment.failed', $fresh, $wallet, ['failure_kind' => 'invalid_amount'])
                : null);

            return $payment->fresh();
        }

        $approved = $payment->fresh();
        if ($approved === null) {
            return $payment;
        }

        try {
            $entry = $this->ledgerService->debit(
                $wallet->fresh() ?? $wallet,
                $amountCents,
                'payment',
                (string) $approved->public_id,
                'Outbound ACH authorized',
            );
        } catch (InsufficientBalanceException) {
            $p = $approved->fresh();
            if ($p === null) {
                return $payment;
            }
            $from = $p->status->getValue();
            $p->status->transitionTo(PaymentFailed::class);
            $p->held_reason = 'balance:insufficient_balance';
            $p->save();
            $freshFail = $p->fresh();
            $this->recordPaymentTransition($freshFail, $from, 'failed', 'payment.insufficient_balance', $wallet, [
                'reason_layer' => 'balance',
                'reason_code' => 'insufficient_balance',
            ], $freshFail !== null
                ? DeveloperWebhookContext::forPayment('payment.failed', $freshFail, $wallet, [
                    'failure_kind' => 'insufficient_balance',
                ])
                : null);

            return $p->fresh();
        }

        $approvedMeta = is_array($approved->metadata) ? $approved->metadata : [];
        $approvedMeta['settlement_ledger_entry_id'] = $entry->getKey();
        $approved->metadata = $approvedMeta;
        $approved->save();

        $bankKey = $payment->idempotency_key !== null && $payment->idempotency_key !== ''
            ? 'pay_'.$payment->idempotency_key
            : 'pay_'.$payment->getKey();

        try {
            $bankResponse = $this->columnBank->achPush(
                $partnerAccountId,
                $amountCents,
                $bankKey,
            );
        } catch (\Throwable $e) {
            $p = $approved->fresh();
            if ($p === null) {
                return $payment;
            }

            $this->reverseSettlementDebitIfPresent($p, 'Outbound ACH failed before settlement');

            $fromProc = $p->status->getValue();
            $p->status->transitionTo(PaymentFailed::class);
            $p->held_reason = 'bank_error';
            $metadata = is_array($p->metadata) ? $p->metadata : [];
            $metadata['bank_error'] = $e->getMessage();
            $p->metadata = $metadata;
            $p->save();
            $freshBank = $p->fresh();
            $this->recordPaymentTransition($freshBank, $fromProc, 'failed', 'payment.bank_error', $wallet, [], $freshBank !== null
                ? DeveloperWebhookContext::forPayment('payment.failed', $freshBank, $wallet, [
                    'failure_kind' => 'bank_error',
                ])
                : null);

            return $p->fresh();
        }

        $p = $approved->fresh();
        if ($p === null) {
            return $payment;
        }

        $transferId = isset($bankResponse['transfer_id']) ? (string) $bankResponse['transfer_id'] : null;
        $metadata = is_array($p->metadata) ? $p->metadata : [];
        $metadata['bank_transfer_id'] = $transferId;
        $metadata['bank_response'] = $bankResponse;
        $p->metadata = $metadata;

        $fromBank = $p->status->getValue();
        $p->status->transitionTo(PaymentProcessing::class);
        $p->save();
        $freshProc = $p->fresh();
        $this->recordPaymentTransition($freshProc, $fromBank, 'processing', 'payment.processing', $wallet, [
            'bank_transfer_id' => $transferId,
        ], $freshProc !== null
            ? DeveloperWebhookContext::forPayment('payment.processing', $freshProc, $wallet, array_filter([
                'bank_transfer_id' => $transferId,
            ]))
            : null);

        return $p->fresh();
    }

    private function applyHoldDecision(Payment $payment, SpendDecision $decision, WalletAccount $wallet, int $amountCents): Payment
    {
        $holdType = $decision->holdType;

        if ($holdType === 'hold_approval' && $decision->approvalRequestId !== null) {
            $from = $payment->status->getValue();
            $payment->status->transitionTo(PaymentHeldApproval::class);
            $payment->held_reason = 'hold_approval';
            $metadata = is_array($payment->metadata) ? $payment->metadata : [];
            $metadata['approval_request_id'] = $decision->approvalRequestId;
            $metadata['approval_token'] = $decision->approvalToken;
            $payment->metadata = $metadata;
            $payment->save();
            $heldFresh = $payment->fresh();
            $this->recordPaymentTransition($heldFresh, $from, 'held_approval', 'payment.held_for_approval', $wallet, [
                'approval_request_id' => (string) $decision->approvalRequestId,
            ], $heldFresh !== null
                ? DeveloperWebhookContext::forPayment('payment.held.approval_required', $heldFresh, $wallet, [
                    'approval_request_id' => (string) $decision->approvalRequestId,
                ])
                : null);

            $freshPayment = $payment->fresh();
            $freshWallet = $wallet->fresh();
            $token = $decision->approvalToken;
            if (
                $freshPayment !== null
                && $freshWallet !== null
                && is_string($token)
                && $token !== ''
            ) {
                $routeName = (string) config('budera.urls.payment_approval_show_route', 'payment-approvals.show');
                $approvalUrl = route($routeName, ['token' => $token]);
                $user = $freshWallet->user()->first();
                TransactionalMail::notifyUser($user, new PaymentHeldForApprovalNotification($freshPayment, $freshWallet, $approvalUrl));
            }

            return $payment->fresh();
        }

        $targetState = PaymentHeldAnomaly::class;
        $toState = 'held_anomaly';
        $action = 'payment.held_anomaly';
        $from = $payment->status->getValue();
        $payment->status->transitionTo($targetState);
        $payment->held_reason = match ($holdType) {
            'needs_topup' => 'needs_topup',
            'hold_anomaly' => 'hold_anomaly',
            default => $holdType ?? 'held',
        };
        $payment->save();
        $anomalyFresh = $payment->fresh();
        $this->recordPaymentTransition($anomalyFresh, $from, $toState, $action, $wallet, [
            'hold_type' => $holdType,
        ], $anomalyFresh !== null
            ? DeveloperWebhookContext::forPayment('payment.held.anomaly', $anomalyFresh, $wallet, [
                'hold_type' => $holdType,
            ])
            : null);

        if ($holdType === 'needs_topup') {
            $freshPayment = $payment->fresh();
            $freshWallet = $wallet->fresh();
            if ($freshPayment !== null && $freshWallet !== null) {
                $balanceCents = (int) $freshWallet->balance_cents;
                $user = $freshWallet->user()->first();
                TransactionalMail::notifyUser($user, new LowBalanceNotification($freshPayment, $freshWallet, $amountCents, $balanceCents));
            }
        }

        return $payment->fresh();
    }

    /**
     * @param  array<string, mixed>  $extraMeta
     * @param  array<string, mixed>|null  $webhookContext  e.g. {@see DeveloperWebhookContext::forPayment()}
     */
    private function recordPaymentTransition(
        ?Payment $payment,
        string $fromState,
        string $toState,
        string $action,
        WalletAccount $wallet,
        array $extraMeta = [],
        ?array $webhookContext = null,
    ): void {
        if ($payment === null) {
            return;
        }

        $context = [
            'stream' => 'developer',
            'actor_type' => 'system',
            'actor_id' => null,
            'action' => $action,
            'resource_type' => 'payments',
            'resource_id' => (string) $payment->getKey(),
            'environment' => $payment->environment,
            'account_id' => (int) $wallet->getKey(),
            'metadata' => array_merge([
                'company_id' => (string) $wallet->company_id,
                'wallet_account_id' => (string) $wallet->getKey(),
            ], $extraMeta),
        ];

        if ($webhookContext !== null) {
            $context = array_merge($context, $webhookContext);
        }

        $this->transitionRecorder->record(
            $payment,
            $fromState,
            $toState,
            $context,
        );
    }

    private function reverseSettlementDebitIfPresent(Payment $payment, string $description): void
    {
        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        $entryId = $metadata['settlement_ledger_entry_id'] ?? null;

        if ($entryId === null) {
            return;
        }

        $entry = LedgerEntry::query()->whereKey((int) $entryId)->first();

        if (! $entry instanceof LedgerEntry) {
            return;
        }

        $this->ledgerService->reversal($entry, $description);
    }
}
