<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiErrorResponse;
use App\Models\BankWebhookEvent;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Topup;
use App\Models\WalletKycVerification;
use App\Notifications\Transactional\KycNeedsInfoNotification;
use App\Notifications\Transactional\KycRejectedNotification;
use App\Services\Audit\TransitionRecorder;
use App\Services\Banking\PartnerBankIntegrationResolver;
use App\Services\Banking\WalletProvisioningService;
use App\Services\Ledger\LedgerService;
use App\Services\Mail\TransactionalMail;
use App\Services\Webhooks\DeveloperWebhookContext;
use App\States\Payment\PaymentFailed;
use App\States\Payment\PaymentProcessing;
use App\States\Payment\PaymentReturned;
use App\States\Payment\PaymentSettled;
use App\States\Topup\TopupFailed;
use App\States\Topup\TopupProcessing;
use App\States\Topup\TopupSettled;
use App\States\WalletKycVerification\WalletKycVerificationApproved;
use App\States\WalletKycVerification\WalletKycVerificationNeedsInfo;
use App\States\WalletKycVerification\WalletKycVerificationRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MockBankWebhookController extends Controller
{
    public function handle(
        Request $request,
        PartnerBankIntegrationResolver $resolver,
        TransitionRecorder $transitionRecorder,
        LedgerService $ledgerService,
        WalletProvisioningService $walletProvisioning,
    ): JsonResponse {
        $resolved = $resolver->resolveForProvider('mock_bank');
        $secret = $resolved['inbound_webhook_secret'];
        if ($secret === null || $secret === '') {
            Log::warning('mock_bank_webhook_received_but_secret_not_configured');

            return ApiErrorResponse::json('webhook_not_configured');
        }

        $raw = $request->getContent();
        $signatureHeader = $request->header('X-Signature', '');
        $expected = hash_hmac('sha256', $raw, $secret);

        $provided = null;
        if (preg_match('/^sha256=(.+)$/i', $signatureHeader, $matches) === 1) {
            $provided = $matches[1];
        }

        if ($provided === null || ! hash_equals($expected, $provided)) {
            return ApiErrorResponse::json('invalid_signature');
        }

        $payload = $request->all();
        $event = (string) ($payload['event'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $transferId = isset($data['transfer_id']) ? (string) $data['transfer_id'] : null;
        $kycId = isset($data['kyc_submission_id']) ? (string) $data['kyc_submission_id'] : null;

        BankWebhookEvent::query()->create([
            'event' => $event !== '' ? $event : 'unknown',
            'payload' => $payload,
            'transfer_id' => $transferId,
            'mock_kyc_submission_id' => $kycId,
        ]);

        if ($event === 'kyc.verified' && $kycId !== null) {
            DB::transaction(function () use ($kycId, $transitionRecorder, $walletProvisioning): void {
                $kyc = WalletKycVerification::query()
                    ->where('mock_kyc_submission_id', $kycId)
                    ->lockForUpdate()
                    ->first();

                if ($kyc === null) {
                    return;
                }

                $kyc->status->transitionTo(WalletKycVerificationApproved::class);
                $kyc->verified_at = now();
                $kyc->save();

                $walletAccount = $kyc->walletAccount()->first();

                $transitionRecorder->record(
                    $kyc,
                    'pending',
                    'approved',
                    [
                        'stream' => 'agent_bank',
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'action' => 'wallet.kyc.approved',
                        'resource_type' => 'wallet_kyc_verifications',
                        'resource_id' => (string) $kyc->getKey(),
                        'environment' => $walletAccount?->environment ?? 'sandbox',
                        'metadata' => [
                            'wallet_account_id' => (string) $kyc->wallet_account_id,
                            'mock_kyc_submission_id' => $kyc->mock_kyc_submission_id,
                        ],
                        'webhook_event' => 'kyc.approved',
                        'webhook_payload' => [
                            'event' => 'kyc.approved',
                            'data' => [
                                'wallet_account_id' => $walletAccount ? (string) $walletAccount->public_id : (string) $kyc->wallet_account_id,
                                'kyc_verification_id' => (string) $kyc->getKey(),
                                'company_id' => $walletAccount ? (string) $walletAccount->company_id : null,
                            ],
                        ],
                    ],
                );

                $walletProvisioning->activateWalletPartnerAccountAfterKyc($kyc->fresh());
            });
        }

        if ($event === 'kyc.needs_info' && $kycId !== null) {
            $kyc = WalletKycVerification::query()
                ->where('mock_kyc_submission_id', $kycId)
                ->first();

            if ($kyc !== null) {
                $fromKycState = $kyc->status->getValue();
                $kyc->status->transitionTo(WalletKycVerificationNeedsInfo::class);
                $kyc->save();

                $walletAccount = $kyc->walletAccount()->first();

                $transitionRecorder->record(
                    $kyc,
                    $fromKycState,
                    'needs_info',
                    [
                        'stream' => 'agent_bank',
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'action' => 'wallet.kyc.needs_info',
                        'resource_type' => 'wallet_kyc_verifications',
                        'resource_id' => (string) $kyc->getKey(),
                        'environment' => $walletAccount?->environment ?? 'sandbox',
                        'metadata' => [
                            'wallet_account_id' => (string) $kyc->wallet_account_id,
                            'mock_kyc_submission_id' => $kyc->mock_kyc_submission_id,
                        ],
                        'webhook_event' => 'kyc.needs_info',
                        'webhook_payload' => [
                            'event' => 'kyc.needs_info',
                            'data' => [
                                'wallet_account_id' => $walletAccount ? (string) $walletAccount->public_id : (string) $kyc->wallet_account_id,
                                'kyc_verification_id' => (string) $kyc->getKey(),
                                'company_id' => $walletAccount ? (string) $walletAccount->company_id : null,
                            ],
                        ],
                    ],
                );

                if ($walletAccount !== null) {
                    $user = $walletAccount->user()->first();
                    TransactionalMail::notifyUser($user, new KycNeedsInfoNotification($walletAccount, $kyc->fresh()));
                }
            }
        }

        if ($event === 'kyc.rejected' && $kycId !== null) {
            $kyc = WalletKycVerification::query()
                ->where('mock_kyc_submission_id', $kycId)
                ->first();

            if ($kyc !== null) {
                $kyc->status->transitionTo(WalletKycVerificationRejected::class);
                $kyc->verified_at = null;
                $kyc->save();

                $walletAccount = $kyc->walletAccount()->first();

                $transitionRecorder->record(
                    $kyc,
                    'pending',
                    'rejected',
                    [
                        'stream' => 'agent_bank',
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'action' => 'wallet.kyc.rejected',
                        'resource_type' => 'wallet_kyc_verifications',
                        'resource_id' => (string) $kyc->getKey(),
                        'environment' => $walletAccount?->environment ?? 'sandbox',
                        'metadata' => [
                            'wallet_account_id' => (string) $kyc->wallet_account_id,
                            'mock_kyc_submission_id' => $kyc->mock_kyc_submission_id,
                        ],
                        'webhook_event' => 'kyc.failed',
                        'webhook_payload' => [
                            'event' => 'kyc.failed',
                            'data' => [
                                'wallet_account_id' => (string) $kyc->wallet_account_id,
                                'kyc_verification_id' => (string) $kyc->getKey(),
                                'company_id' => $walletAccount ? (string) $walletAccount->company_id : null,
                            ],
                        ],
                    ],
                );

                if ($walletAccount !== null) {
                    $user = $walletAccount->user()->first();
                    TransactionalMail::notifyUser($user, new KycRejectedNotification($walletAccount, $kyc->fresh()));
                }
            }
        }

        if (in_array($event, ['transfer.ach.settled', 'transfer.ach.failed', 'transfer.ach.returned'], true) && $transferId !== null) {
            $this->handleAchTransferWebhook($event, $data, $ledgerService, $transitionRecorder);
        }

        Log::info('mock_bank_webhook', ['event' => $event]);

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleAchTransferWebhook(string $event, array $data, LedgerService $ledger, TransitionRecorder $transitionRecorder): void
    {
        $transferId = isset($data['transfer_id']) ? (string) $data['transfer_id'] : null;

        if ($transferId === null || $transferId === '') {
            return;
        }

        $direction = isset($data['direction']) ? (string) $data['direction'] : '';
        $amountCents = isset($data['amount_cents']) ? (int) $data['amount_cents'] : 0;

        if ($amountCents <= 0) {
            return;
        }

        $payment = Payment::query()
            ->where('metadata->bank_transfer_id', $transferId)
            ->first();

        $topup = Topup::query()
            ->where('metadata->bank_transfer_id', $transferId)
            ->first();

        if ($event === 'transfer.ach.returned') {
            if ($direction === 'credit' && $payment !== null && $payment->status instanceof PaymentSettled) {
                $metadata = is_array($payment->metadata) ? $payment->metadata : [];
                $ledgerEntryId = $metadata['settlement_ledger_entry_id'] ?? null;
                if ($ledgerEntryId === null) {
                    return;
                }

                $originalEntry = LedgerEntry::query()->whereKey((int) $ledgerEntryId)->first();
                if ($originalEntry === null) {
                    return;
                }

                DB::transaction(function () use ($payment, $ledger, $originalEntry, $transitionRecorder, $amountCents): void {
                    $wallet = $payment->walletAccount()->firstOrFail();
                    $from = $payment->status->getValue();
                    $ledger->reversal($originalEntry, 'ACH return (simulated)');
                    $payment->status->transitionTo(PaymentReturned::class);
                    $payment->save();

                    $fresh = $payment->fresh();
                    if ($fresh !== null) {
                        $transitionRecorder->record(
                            $fresh,
                            $from,
                            'returned',
                            array_merge(
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
                            ),
                        );
                    }
                });
            }

            return;
        }

        if ($event === 'transfer.ach.settled') {
            if ($direction === 'credit' && $payment !== null && $payment->status instanceof PaymentProcessing) {
                DB::transaction(function () use ($payment, $ledger, $amountCents, $transitionRecorder): void {
                    $from = $payment->status->getValue();
                    $wallet = $payment->walletAccount()->firstOrFail();
                    $metadata = is_array($payment->metadata) ? $payment->metadata : [];
                    $entryId = $metadata['settlement_ledger_entry_id'] ?? null;

                    if ($entryId === null || LedgerEntry::query()->whereKey((int) $entryId)->doesntExist()) {
                        $entry = $ledger->debit(
                            $wallet,
                            $amountCents,
                            'payment',
                            (string) $payment->public_id,
                            'Outbound ACH settled',
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
                        $transitionRecorder->record(
                            $fresh,
                            $from,
                            'settled',
                            array_merge(
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
                            ),
                        );
                    }
                });
            }

            if ($direction === 'debit' && $topup !== null && $topup->status instanceof TopupProcessing) {
                DB::transaction(function () use ($topup, $ledger, $amountCents, $transitionRecorder): void {
                    $wallet = $topup->walletAccount()->firstOrFail();

                    $entry = $ledger->credit(
                        $wallet,
                        $amountCents,
                        'topup',
                        (string) Str::uuid(),
                        'Inbound ACH topup settled',
                    );

                    $from = $topup->status->getValue();
                    $topup->status->transitionTo(TopupSettled::class);
                    $topup->settled_at = now();
                    $metadata = is_array($topup->metadata) ? $topup->metadata : [];
                    $metadata['ledger_entry_id'] = $entry->getKey();
                    $topup->metadata = $metadata;
                    $topup->save();

                    $fresh = $topup->fresh();
                    if ($fresh !== null) {
                        $transitionRecorder->record(
                            $fresh,
                            $from,
                            'settled',
                            array_merge(
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
                            ),
                        );
                    }
                });
            }

            return;
        }

        if ($event === 'transfer.ach.failed') {
            if ($direction === 'credit' && $payment !== null && $payment->status instanceof PaymentProcessing) {
                DB::transaction(function () use ($payment, $ledger, $data, $transitionRecorder): void {
                    $from = $payment->status->getValue();
                    $wallet = $payment->walletAccount()->firstOrFail();
                    $metadata = is_array($payment->metadata) ? $payment->metadata : [];

                    $ledgerEntryId = $metadata['settlement_ledger_entry_id'] ?? null;
                    $entry = $ledgerEntryId !== null
                        ? LedgerEntry::query()->whereKey((int) $ledgerEntryId)->first()
                        : null;

                    if ($entry instanceof LedgerEntry) {
                        $ledger->reversal($entry, 'Outbound ACH failed');
                    }

                    $payment->status->transitionTo(PaymentFailed::class);
                    $payment->held_reason = 'ach_failed';
                    $metadata['ach_failure'] = $data;
                    $payment->metadata = $metadata;
                    $payment->save();

                    $fresh = $payment->fresh();
                    if ($fresh !== null) {
                        $transitionRecorder->record(
                            $fresh,
                            $from,
                            'failed',
                            array_merge(
                                [
                                    'stream' => 'agent_bank',
                                    'actor_type' => 'system',
                                    'actor_id' => null,
                                    'action' => 'payment.ach_failed',
                                    'resource_type' => 'payments',
                                    'resource_id' => (string) $fresh->getKey(),
                                    'environment' => $fresh->environment,
                                    'account_id' => (int) $wallet->getKey(),
                                    'metadata' => [
                                        'company_id' => (string) $wallet->company_id,
                                        'wallet_account_id' => (string) $wallet->getKey(),
                                    ],
                                ],
                                DeveloperWebhookContext::forPayment('payment.failed', $fresh, $wallet, [
                                    'failure_kind' => 'ach_failed',
                                ]),
                            ),
                        );
                    }
                });
            }

            if ($direction === 'debit' && $topup !== null && $topup->status instanceof TopupProcessing) {
                $from = $topup->status->getValue();
                $topup->status->transitionTo(TopupFailed::class);
                $metadata = is_array($topup->metadata) ? $topup->metadata : [];
                $metadata['ach_failure'] = $data;
                $topup->metadata = $metadata;
                $topup->save();

                $wallet = $topup->walletAccount()->first();
                $fresh = $topup->fresh();
                if ($fresh !== null && $wallet !== null) {
                    $transitionRecorder->record(
                        $fresh,
                        $from,
                        'failed',
                        array_merge(
                            [
                                'stream' => 'agent_bank',
                                'actor_type' => 'system',
                                'actor_id' => null,
                                'action' => 'topup.ach_failed',
                                'resource_type' => 'topups',
                                'resource_id' => (string) $fresh->getKey(),
                                'environment' => $fresh->environment,
                                'account_id' => (int) $wallet->getKey(),
                                'metadata' => [
                                    'company_id' => (string) $wallet->company_id,
                                    'wallet_account_id' => (string) $wallet->getKey(),
                                ],
                            ],
                            DeveloperWebhookContext::forTopup('topup.failed', $fresh, $wallet, [
                                'failure_kind' => 'ach_failed',
                            ]),
                        ),
                    );
                }
            }
        }
    }
}
