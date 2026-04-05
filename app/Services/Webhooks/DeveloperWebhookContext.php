<?php

namespace App\Services\Webhooks;

use App\Models\Payment;
use App\Models\Topup;
use App\Models\WalletAccount;

/**
 * Builds TransitionRecorder context keys for developer-facing outbound webhooks.
 */
final class DeveloperWebhookContext
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{webhook_event: string, webhook_payload: array{event: string, data: array<string, mixed>}}
     */
    public static function forPayment(string $event, Payment $payment, WalletAccount $wallet, array $data = []): array
    {
        return [
            'webhook_event' => $event,
            'webhook_payload' => [
                'event' => $event,
                'data' => array_merge([
                    'payment_id' => $payment->public_id,
                    'wallet_account_id' => $wallet->public_id,
                    'environment' => (string) $payment->environment,
                    'company_id' => (string) $wallet->company_id,
                    'amount_usd' => (string) $payment->amount_usd,
                    'status' => $payment->status->getValue(),
                ], $data),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{webhook_event: string, webhook_payload: array{event: string, data: array<string, mixed>}}
     */
    public static function forTopup(string $event, Topup $topup, WalletAccount $wallet, array $data = []): array
    {
        return [
            'webhook_event' => $event,
            'webhook_payload' => [
                'event' => $event,
                'data' => array_merge([
                    'topup_id' => $topup->public_id,
                    'wallet_account_id' => $wallet->public_id,
                    'environment' => (string) $topup->environment,
                    'company_id' => (string) $wallet->company_id,
                    'amount_usd' => (string) $topup->amount_usd,
                    'status' => $topup->status->getValue(),
                ], $data),
            ],
        ];
    }
}
