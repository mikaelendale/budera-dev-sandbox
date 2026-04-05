<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiErrorResponse;
use App\Jobs\ProcessWebhookDeliveryJob;
use App\Models\ApiKey;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Audit\AuditService;
use App\Services\Audit\CorrelationId;
use App\Services\Webhooks\WebhookOutboxPayloadFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CompanyWebhookEndpointTestController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function __invoke(
        Request $request,
        WebhookEndpoint $webhookEndpoint,
        WebhookOutboxPayloadFactory $payloadFactory,
    ): JsonResponse {
        $user = $request->user();
        if ($user === null) {
            return ApiErrorResponse::json('unauthenticated_api', 401);
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');
        if (! $apiKey instanceof ApiKey) {
            $apiKey = Auth::guard('api-key')->currentApiKey();
        }

        if ($apiKey instanceof ApiKey) {
            if ((int) $apiKey->company_id !== (int) $webhookEndpoint->company_id) {
                return ApiErrorResponse::json('forbidden');
            }
        } else {
            $this->authorizeForUser($user, 'update', $webhookEndpoint);
        }

        if (! $webhookEndpoint->is_active) {
            return response()->json([
                'ok' => false,
                'error' => 'endpoint_inactive',
            ], 422);
        }

        $eventId = (string) Str::uuid();
        $normalized = $payloadFactory->forEvent('test.ping', [
            'event' => 'test.ping',
            'event_id' => $eventId,
            'created_at' => now()->toIso8601String(),
            'environment' => $webhookEndpoint->environment,
            'data' => ['message' => 'Budera webhook test ping'],
        ]);

        $delivery = WebhookDelivery::query()->create([
            'webhook_outbox_id' => null,
            'webhook_endpoint_id' => $webhookEndpoint->getKey(),
            'event' => 'test.ping',
            'event_id' => $eventId,
            'payload' => $normalized,
            'status' => 'queued',
        ]);

        ProcessWebhookDeliveryJob::dispatchSync(
            $delivery->getKey(),
            CorrelationId::current($request),
        );
        $delivery->refresh();

        $user = $request->user();
        if ($user !== null) {
            $this->auditService->recordDomainAudit([
                'stream' => 'developer',
                'actor_type' => 'user',
                'actor_id' => (string) $user->getKey(),
                'action' => 'webhook_endpoint.test_sent',
                'resource_type' => 'webhook_endpoints',
                'resource_id' => (string) $webhookEndpoint->getKey(),
                'environment' => $webhookEndpoint->environment,
                'metadata' => [
                    'company_id' => (string) $webhookEndpoint->company_id,
                    'delivery_id' => (string) $delivery->getKey(),
                    'delivery_status' => (string) $delivery->status,
                ],
                'correlation_id' => CorrelationId::current($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return response()->json([
            'ok' => $delivery->status === 'delivered',
            'delivery_id' => (string) $delivery->getKey(),
            'status' => (string) $delivery->status,
            'response_status' => $delivery->response_status,
        ]);
    }
}
