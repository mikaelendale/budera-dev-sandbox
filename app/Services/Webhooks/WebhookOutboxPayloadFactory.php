<?php

namespace App\Services\Webhooks;

use Illuminate\Support\Str;

class WebhookOutboxPayloadFactory
{
    /**
     * Normalize webhook payload shape to what our consumers expect.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function forEvent(string $event, array $payload): array
    {
        $data = $this->extractData($payload);

        return [
            'event' => $event,
            'event_id' => is_string($payload['event_id'] ?? null) && ($payload['event_id'] ?? '') !== ''
                ? (string) $payload['event_id']
                : (string) Str::uuid(),
            'created_at' => is_string($payload['created_at'] ?? null) && ($payload['created_at'] ?? '') !== ''
                ? (string) $payload['created_at']
                : now()->toIso8601String(),
            'environment' => is_string($payload['environment'] ?? null) ? (string) $payload['environment'] : null,
            'data' => $this->normalizeDataForEvent($event, $data, $payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractData(array $payload): array
    {
        $data = $payload['data'] ?? [];

        if (is_array($data)) {
            return $data;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeDataForEvent(string $event, array $data, array $payload): array
    {
        // Initial event->payload mapping. If the stored payload already matches, this keeps it as-is.
        return match ($event) {
            'account.active' => [
                'wallet_account_id' => $data['wallet_account_id'] ?? null,
            ],
            'kyc.approved' => [
                'wallet_account_id' => $data['wallet_account_id'] ?? null,
                'kyc_verification_id' => $data['kyc_verification_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
            ],
            'kyc.failed' => [
                'wallet_account_id' => $data['wallet_account_id'] ?? null,
                'kyc_verification_id' => $data['kyc_verification_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
            ],
            'test.ping' => [
                'message' => $data['message'] ?? 'Budera webhook test ping',
            ],
            default => $data,
        };
    }
}
