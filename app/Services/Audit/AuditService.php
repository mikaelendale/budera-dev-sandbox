<?php

namespace App\Services\Audit;

use App\Models\DomainAuditLog;
use App\Models\WebhookOutbox;
use App\Services\Webhooks\WebhookFanOutService;
use Illuminate\Support\Str;

class AuditService
{
    public function __construct(
        private readonly WebhookFanOutService $webhookFanOutService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordDomainAudit(array $data): DomainAuditLog
    {
        return DomainAuditLog::query()->create([
            'stream' => $data['stream'],
            'actor_type' => $data['actor_type'] ?? 'system',
            'actor_id' => $data['actor_id'] ?? null,
            'action' => $data['action'],
            'resource_type' => $data['resource_type'] ?? null,
            'resource_id' => $data['resource_id'] ?? null,
            'environment' => $data['environment'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'correlation_id' => $data['correlation_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function enqueueWebhook(string $event, array $payload, array $context): WebhookOutbox
    {
        $companyId = null;
        $fromContext = $context['company_id'] ?? null;
        if (is_int($fromContext)) {
            $companyId = $fromContext;
        } elseif (is_string($fromContext) && ctype_digit($fromContext)) {
            $companyId = (int) $fromContext;
        }

        if ($companyId === null) {
            $data = $payload['data'] ?? null;
            if (is_array($data) && isset($data['company_id'])) {
                $raw = $data['company_id'];
                if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
                    $companyId = (int) $raw;
                }
            }
        }

        $outbox = WebhookOutbox::query()->create([
            'company_id' => $companyId,
            'event' => $event,
            'event_id' => $context['event_id'] ?? (string) Str::uuid(),
            'environment' => $context['environment'] ?? null,
            'payload' => $payload,
            'destination_url' => $context['destination_url'] ?? null,
            'destination_key' => $context['destination_key'] ?? null,
            'status' => 'queued',
        ]);

        $this->webhookFanOutService->fanOut($outbox);

        $hasLegacyDestination = is_string($outbox->destination_url) && $outbox->destination_url !== ''
            && is_string($outbox->destination_key) && $outbox->destination_key !== '';

        if (! $hasLegacyDestination) {
            $outbox->forceFill(['status' => 'routed'])->save();
        }

        return $outbox->fresh() ?? $outbox;
    }
}
