<?php

namespace App\Services\Audit;

use App\Models\AuthorizationLedgerEntry;
use App\Models\StateTransition;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

class TransitionRecorder
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly DatabaseManager $db,
        private readonly CryptoSigner $cryptoSigner,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        object $model,
        string $fromState,
        string $toState,
        array $context,
    ): void {
        $correlationId = is_string($context['correlation_id'] ?? null) && ($context['correlation_id'] ?? '') !== ''
            ? (string) $context['correlation_id']
            : CorrelationId::fromRequestOrGenerate();

        $actorType = $context['actor_type'] ?? 'system';
        $actorId = $context['actor_id'] ?? null;
        $stream = $context['stream'] ?? 'developer';
        $action = $context['action'] ?? 'state.transitioned';
        $resourceType = $context['resource_type'] ?? null;
        $resourceId = $context['resource_id'] ?? null;
        $environment = $context['environment'] ?? null;
        $metadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];
        $ipAddress = $context['ip_address'] ?? request()?->ip();
        $userAgent = $context['user_agent'] ?? request()?->userAgent();
        $accountId = $context['account_id'] ?? null;
        if ($accountId !== null) {
            $accountId = is_numeric($accountId) ? (int) $accountId : null;
        }

        $webhookEvent = $context['webhook_event'] ?? null;
        $webhookPayload = is_array($context['webhook_payload'] ?? null) ? $context['webhook_payload'] : null;
        $webhookDestinationUrl = is_string($context['webhook_destination_url'] ?? null) ? (string) $context['webhook_destination_url'] : null;
        $webhookDestinationKey = is_string($context['webhook_destination_key'] ?? null) ? (string) $context['webhook_destination_key'] : null;

        $modelType = method_exists($model, 'getMorphClass')
            ? $model->getMorphClass()
            : get_class($model);
        $modelId = (string) ($model->getKey() ?? '');

        $authorization = [
            'stream' => $stream,
            'actor' => [
                'type' => (string) $actorType,
                'id' => $actorId !== null ? (string) $actorId : null,
            ],
            'action' => $action,
            'resource' => [
                'type' => $resourceType ?? $modelType,
                'id' => $resourceId ?? $modelId,
            ],
            'model' => [
                'type' => $modelType,
                'id' => $modelId,
            ],
            'state' => [
                'from' => $fromState,
                'to' => $toState,
            ],
            'correlation_id' => $correlationId,
            'environment' => $environment,
        ];

        if ($metadata !== []) {
            $authorization['metadata'] = $metadata;
        }

        $signed = $this->cryptoSigner->sign($authorization);

        $this->db->transaction(function () use (
            $model,
            $fromState,
            $toState,
            $actorType,
            $actorId,
            $stream,
            $action,
            $resourceType,
            $resourceId,
            $environment,
            $metadata,
            $correlationId,
            $webhookEvent,
            $webhookPayload,
            $webhookDestinationUrl,
            $webhookDestinationKey,
            $signed,
            $modelType,
            $modelId,
            $ipAddress,
            $userAgent,
            $accountId,
        ): void {
            $authorizationLedgerEntry = AuthorizationLedgerEntry::query()->create([
                'stream' => $stream,
                'actor_type' => (string) $actorType,
                'actor_id' => $actorId !== null ? (string) $actorId : null,
                'authorization_text' => (string) $signed['authorization_text'],
                'authorization_hash' => (string) $signed['authorization_hash'],
                'authorization_signature' => (string) $signed['authorization_signature'],
                'correlation_id' => $correlationId,
                'environment' => $environment,
                'ip_address' => is_string($ipAddress) ? $ipAddress : null,
                'user_agent' => is_string($userAgent) ? $userAgent : null,
                'account_id' => $accountId,
                'metadata' => array_merge($metadata, [
                    'from_state' => $fromState,
                    'to_state' => $toState,
                    'action' => $action,
                ]),
            ]);

            $domainAudit = $this->auditService->recordDomainAudit([
                'stream' => $stream,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'action' => $action,
                'resource_type' => $resourceType ?? get_class($model),
                'resource_id' => $resourceId ?? (string) ($model->getKey() ?? ''),
                'environment' => $environment,
                'metadata' => array_merge($metadata, [
                    'from_state' => $fromState,
                    'to_state' => $toState,
                    'authorization_ledger_id' => (string) $authorizationLedgerEntry->getKey(),
                    'authorization_hash' => (string) $signed['authorization_hash'],
                ]),
                'correlation_id' => $correlationId,
                'ip_address' => is_string($ipAddress) ? $ipAddress : request()?->ip(),
                'user_agent' => is_string($userAgent) ? $userAgent : request()?->userAgent(),
            ]);

            StateTransition::query()->create([
                'model_type' => $modelType,
                'model_id' => $modelId,
                'from_state' => $fromState,
                'to_state' => $toState,
                'actor_type' => $actorType,
                'actor_id' => $actorId !== null ? (string) $actorId : null,
                'metadata' => [
                    'domain_audit_id' => (string) $domainAudit->getKey(),
                    'authorization_ledger_id' => (string) $authorizationLedgerEntry->getKey(),
                    'authorization_hash' => (string) $signed['authorization_hash'],
                    ...$metadata,
                ],
            ]);

            if (is_string($webhookEvent) && $webhookPayload !== null) {
                $webhookEventId = (string) Str::uuid();

                $companyIdForWebhook = $metadata['company_id'] ?? null;
                if (is_string($companyIdForWebhook) && ctype_digit($companyIdForWebhook)) {
                    $companyIdForWebhook = (int) $companyIdForWebhook;
                } elseif (! is_int($companyIdForWebhook)) {
                    $companyIdForWebhook = null;
                }

                $this->auditService->enqueueWebhook(
                    $webhookEvent,
                    $webhookPayload + [
                        'event_id' => $webhookEventId,
                        'created_at' => now()->toIso8601String(),
                        'environment' => $environment,
                    ],
                    [
                        'event_id' => $webhookEventId,
                        'environment' => $environment,
                        'company_id' => $companyIdForWebhook,
                        'destination_url' => $webhookDestinationUrl,
                        'destination_key' => $webhookDestinationKey,
                    ],
                );
            }
        });
    }
}
