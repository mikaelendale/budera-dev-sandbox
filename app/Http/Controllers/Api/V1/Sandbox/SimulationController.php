<?php

namespace App\Http\Controllers\Api\V1\Sandbox;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sandbox\SimulationAchReturnRequest;
use App\Http\Requests\Api\V1\Sandbox\SimulationKycApproveRequest;
use App\Http\Requests\Api\V1\Sandbox\SimulationMicrodepositRequest;
use App\Http\Requests\Api\V1\Sandbox\SimulationSettlementRequest;
use App\Http\Responses\ApiErrorResponse;
use App\Models\BankLink;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Topup;
use App\Models\WalletKycVerification;
use App\Services\Audit\TransitionRecorder;
use App\Services\Banking\MockBankClient;
use App\Services\Banking\WalletProvisioningService;
use App\Services\Ledger\LedgerService;
use App\Services\Webhooks\DeveloperWebhookContext;
use App\States\BankLink\BankLinkMicrodepositSent;
use App\States\Payment\PaymentProcessing;
use App\States\Payment\PaymentReturned;
use App\States\Payment\PaymentSettled;
use App\States\Topup\TopupProcessing;
use App\States\Topup\TopupSettled;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sandbox-only ACH/KYC simulation helpers for developers. Micro-deposit reveal is served from Laravel
 * (not the mock-bank HTTP service) because amounts live in Budera config and bank link metadata.
 *
 * When MOCK_BANK_INLINE is true, settlement and return are handled directly in the DB
 * without relying on the mock-bank HTTP service to send webhook callbacks.
 */
class SimulationController extends Controller
{
    public function __construct(
        private readonly MockBankClient $mockBank,
        private readonly WalletProvisioningService $walletProvisioning,
        private readonly LedgerService $ledgerService,
        private readonly TransitionRecorder $transitionRecorder,
    ) {}

    public function settlement(SimulationSettlementRequest $request): JsonResponse
    {
        $payment = $this->resolvePaymentForSettlement($request);
        $topup = null;

        if ($payment !== null) {
            Gate::authorize('view', $payment);
            if ($payment->environment !== 'sandbox') {
                return ApiErrorResponse::json('sandbox_only');
            }
            if (! $payment->status instanceof PaymentProcessing) {
                return ApiErrorResponse::json('payment_not_processing');
            }

            $transferId = $this->extractBankTransferId($payment);

            if ($this->isInline() || $transferId === null) {
                $this->settlePaymentInline($payment);
            } else {
                try {
                    $this->mockBank->settleNow($transferId);
                } catch (InvalidArgumentException $e) {
                    return ApiErrorResponse::json($e->getMessage());
                }
            }

            return response()->json([
                'ok' => true,
                'resource' => 'payment',
                'payment_id' => $payment->public_id,
                'status' => $payment->fresh()?->status->getValue(),
            ]);
        }

        $topup = $this->resolveTopupForSettlement($request);

        if ($topup !== null) {
            Gate::authorize('view', $topup);
            if ($topup->environment !== 'sandbox') {
                return ApiErrorResponse::json('sandbox_only');
            }
            if (! $topup->status instanceof TopupProcessing) {
                return ApiErrorResponse::json('topup_not_processing');
            }

            $transferId = $this->extractBankTransferId($topup);

            if ($this->isInline() || $transferId === null) {
                $this->settleTopupInline($topup);
            } else {
                try {
                    $this->mockBank->settleNow($transferId);
                } catch (InvalidArgumentException $e) {
                    return ApiErrorResponse::json($e->getMessage());
                }
            }

            return response()->json([
                'ok' => true,
                'resource' => 'topup',
                'topup_id' => $topup->public_id,
                'status' => $topup->fresh()?->status->getValue(),
            ]);
        }

        return ApiErrorResponse::json('resource_not_found');
    }

    public function paymentReturn(SimulationAchReturnRequest $request): JsonResponse
    {
        $payment = $this->resolvePaymentForReturn($request);

        if ($payment === null) {
            return ApiErrorResponse::json('payment_not_found');
        }

        Gate::authorize('view', $payment);

        if ($payment->environment !== 'sandbox') {
            return ApiErrorResponse::json('sandbox_only');
        }

        if (! $payment->status instanceof PaymentSettled) {
            return ApiErrorResponse::json('payment_not_settled');
        }

        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        if (! isset($metadata['settlement_ledger_entry_id'])) {
            return ApiErrorResponse::json('payment_missing_settlement_ledger');
        }

        $transferId = $this->extractBankTransferId($payment);

        if ($this->isInline() || $transferId === null) {
            $this->returnPaymentInline($payment);
        } else {
            try {
                $this->mockBank->achReturn($transferId);
            } catch (InvalidArgumentException $e) {
                return ApiErrorResponse::json($e->getMessage());
            }
        }

        return response()->json([
            'ok' => true,
            'payment_id' => $payment->public_id,
            'status' => $payment->fresh()?->status->getValue(),
        ]);
    }

    public function kycApprove(SimulationKycApproveRequest $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->firstCompany();
        if ($company === null) {
            return ApiErrorResponse::json('company_required');
        }

        $kyc = WalletKycVerification::query()
            ->whereKey((int) $request->validated('wallet_kyc_verification_id'))
            ->firstOrFail();

        $wallet = $kyc->walletAccount;
        if ((int) $wallet->company_id !== (int) $company->getKey()) {
            return ApiErrorResponse::json('forbidden');
        }

        if ($wallet->environment !== 'sandbox') {
            return ApiErrorResponse::json('sandbox_only');
        }

        $this->walletProvisioning->forceApproveKycForSandbox($kyc);

        $kyc->refresh();
        $wallet->refresh();

        return response()->json([
            'ok' => true,
            'status' => $kyc->status->getValue(),
            'wallet_account_id' => $wallet->public_id,
            'wallet_status' => $wallet->status->getValue(),
        ]);
    }

    public function microdeposit(SimulationMicrodepositRequest $request): JsonResponse
    {
        $bankLink = BankLink::query()
            ->where('public_id', $request->validated('bank_link_id'))
            ->firstOrFail();

        Gate::authorize('view', $bankLink);

        if ($bankLink->environment !== 'sandbox') {
            return ApiErrorResponse::json('sandbox_only');
        }

        if (! $bankLink->status instanceof BankLinkMicrodepositSent) {
            return ApiErrorResponse::json('bank_link_not_awaiting_microdeposit');
        }

        $meta = is_array($bankLink->metadata) ? $bankLink->metadata : [];
        $amounts = $meta['microdeposit_expected_cents'] ?? null;
        if (! is_array($amounts) || $amounts === []) {
            $amounts = config('budera.bank_link.sandbox_microdeposit_cents', [12, 34]);
        }

        $normalized = array_values(array_map(static fn ($v): int => (int) $v, $amounts));

        return response()->json([
            'ok' => true,
            'bank_link_id' => $bankLink->public_id,
            'amounts_cents' => $normalized,
        ]);
    }

    private function resolvePaymentForSettlement(SimulationSettlementRequest $request): ?Payment
    {
        if ($request->filled('payment_id')) {
            return Payment::query()->where('public_id', $request->validated('payment_id'))->first();
        }

        $transferId = $request->validated('bank_transfer_id');
        if ($transferId !== null) {
            return Payment::query()->where('metadata->bank_transfer_id', $transferId)->first();
        }

        return null;
    }

    private function resolveTopupForSettlement(SimulationSettlementRequest $request): ?Topup
    {
        if ($request->filled('topup_id')) {
            return Topup::query()->where('public_id', $request->validated('topup_id'))->first();
        }

        $transferId = $request->validated('bank_transfer_id');
        if ($transferId !== null) {
            return Topup::query()->where('metadata->bank_transfer_id', $transferId)->first();
        }

        return null;
    }

    private function resolvePaymentForReturn(SimulationAchReturnRequest $request): ?Payment
    {
        if ($request->filled('payment_id')) {
            return Payment::query()->where('public_id', $request->validated('payment_id'))->first();
        }

        $transferId = $request->validated('bank_transfer_id');
        if ($transferId !== null) {
            return Payment::query()->where('metadata->bank_transfer_id', $transferId)->first();
        }

        return null;
    }

    private function extractBankTransferId(Payment|Topup $model): ?string
    {
        $metadata = is_array($model->metadata) ? $model->metadata : [];

        return isset($metadata['bank_transfer_id']) ? (string) $metadata['bank_transfer_id'] : null;
    }

    private function isInline(): bool
    {
        return (bool) config('services.mock_bank.inline', false);
    }

    private function settlePaymentInline(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $wallet = $payment->walletAccount()->firstOrFail();
            $amountCents = (int) round(($payment->amount_usd ?? 0) * 100);
            $from = $payment->status->getValue();
            $metadata = is_array($payment->metadata) ? $payment->metadata : [];
            $entryId = $metadata['settlement_ledger_entry_id'] ?? null;

            if ($entryId === null || LedgerEntry::query()->whereKey((int) $entryId)->doesntExist()) {
                $entry = $this->ledgerService->debit(
                    $wallet,
                    $amountCents,
                    'payment',
                    (string) $payment->public_id,
                    'Outbound ACH settled (inline sim)',
                );
                $entryId = $entry->getKey();
            }

            $payment->status->transitionTo(PaymentSettled::class);
            $payment->settled_at = now();
            $metadata['settlement_ledger_entry_id'] = $entryId;
            $payment->metadata = $metadata;
            $payment->save();

            $fresh = $payment->fresh();
            if ($fresh !== null) {
                $this->transitionRecorder->record($fresh, $from, 'settled', array_merge(
                    [
                        'stream' => 'agent_bank',
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'action' => 'payment.ach_settled',
                        'resource_type' => 'payments',
                        'resource_id' => (string) $fresh->getKey(),
                        'environment' => $fresh->environment,
                        'account_id' => (int) $wallet->getKey(),
                        'metadata' => [
                            'company_id' => (string) $wallet->company_id,
                            'wallet_account_id' => (string) $wallet->getKey(),
                            'amount_cents' => (string) $amountCents,
                        ],
                    ],
                    DeveloperWebhookContext::forPayment('payment.settled', $fresh, $wallet, [
                        'amount_cents' => (string) $amountCents,
                    ]),
                ));
            }
        });
    }

    private function settleTopupInline(Topup $topup): void
    {
        DB::transaction(function () use ($topup): void {
            $wallet = $topup->walletAccount()->firstOrFail();
            $amountCents = (int) round(($topup->amount_usd ?? 0) * 100);
            $from = $topup->status->getValue();

            $entry = $this->ledgerService->credit(
                $wallet,
                $amountCents,
                'topup',
                (string) Str::uuid(),
                'Inbound ACH topup settled (inline sim)',
            );

            $topup->status->transitionTo(TopupSettled::class);
            $topup->settled_at = now();
            $metadata = is_array($topup->metadata) ? $topup->metadata : [];
            $metadata['ledger_entry_id'] = $entry->getKey();
            $topup->metadata = $metadata;
            $topup->save();

            $fresh = $topup->fresh();
            if ($fresh !== null) {
                $this->transitionRecorder->record($fresh, $from, 'settled', array_merge(
                    [
                        'stream' => 'agent_bank',
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'action' => 'topup.ach_settled',
                        'resource_type' => 'topups',
                        'resource_id' => (string) $fresh->getKey(),
                        'environment' => $fresh->environment,
                        'account_id' => (int) $wallet->getKey(),
                        'metadata' => [
                            'company_id' => (string) $wallet->company_id,
                            'wallet_account_id' => (string) $wallet->getKey(),
                            'amount_cents' => (string) $amountCents,
                        ],
                    ],
                    DeveloperWebhookContext::forTopup('topup.settled', $fresh, $wallet, [
                        'amount_cents' => (string) $amountCents,
                    ]),
                ));
            }
        });
    }

    private function returnPaymentInline(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $wallet = $payment->walletAccount()->firstOrFail();
            $metadata = is_array($payment->metadata) ? $payment->metadata : [];
            $ledgerEntryId = $metadata['settlement_ledger_entry_id'] ?? null;

            $originalEntry = LedgerEntry::query()->whereKey((int) $ledgerEntryId)->first();
            if ($originalEntry === null) {
                return;
            }

            $amountCents = (int) round(($payment->amount_usd ?? 0) * 100);
            $from = $payment->status->getValue();

            $this->ledgerService->reversal($originalEntry, 'ACH return (inline sim)');
            $payment->status->transitionTo(PaymentReturned::class);
            $payment->save();

            $fresh = $payment->fresh();
            if ($fresh !== null) {
                $this->transitionRecorder->record($fresh, $from, 'returned', array_merge(
                    [
                        'stream' => 'agent_bank',
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'action' => 'payment.ach_returned',
                        'resource_type' => 'payments',
                        'resource_id' => (string) $fresh->getKey(),
                        'environment' => $fresh->environment,
                        'account_id' => (int) $wallet->getKey(),
                        'metadata' => [
                            'company_id' => (string) $wallet->company_id,
                            'wallet_account_id' => (string) $wallet->getKey(),
                            'amount_cents' => (string) $amountCents,
                        ],
                    ],
                    DeveloperWebhookContext::forPayment('payment.returned', $fresh, $wallet, [
                        'amount_cents' => (string) $amountCents,
                    ]),
                ));
            }
        });
    }
}
