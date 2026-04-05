<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWebhookEndpointRequest;
use App\Http\Requests\UpdateWebhookEndpointRequest;
use App\Jobs\ProcessWebhookDeliveryJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Audit\AuditService;
use App\Services\Audit\CorrelationId;
use App\Services\Webhooks\WebhookOutboxPayloadFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompanyWebhookEndpointController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $company = $request->user()?->firstCompany();

        if ($company === null) {
            return redirect()->route('dashboard');
        }

        $this->authorize('viewAny', WebhookEndpoint::class);

        $endpoints = WebhookEndpoint::query()
            ->where('company_id', $company->id)
            ->latest()
            ->get()
            ->map(fn (WebhookEndpoint $e): array => [
                'id' => $e->getKey(),
                'url' => $e->url,
                'events' => $e->events ?? [],
                'environment' => $e->environment,
                'is_active' => (bool) $e->is_active,
                'created_at' => $e->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $endpointIds = array_column($endpoints, 'id');

        $recentDeliveries = [];

        if ($endpointIds !== []) {
            $recentDeliveries = WebhookDelivery::query()
                ->whereIn('webhook_endpoint_id', $endpointIds)
                ->with(['webhookEndpoint' => fn ($q) => $q->withoutCompanyScope()->where('company_id', $company->id)])
                ->latest()
                ->limit(25)
                ->get()
                ->map(fn (WebhookDelivery $d): array => [
                    'id' => $d->getKey(),
                    'event' => $d->event,
                    'status' => $d->status,
                    'attempts' => (int) $d->attempts,
                    'response_status' => $d->response_status,
                    'last_attempted_at' => $d->last_attempted_at?->toIso8601String(),
                    'endpoint_id' => $d->webhook_endpoint_id,
                ])
                ->values()
                ->all();
        }

        return Inertia::render('company/webhooks', [
            'endpoints' => $endpoints,
            'allowedEvents' => config('budera.outbound_webhook_events', []),
            'recentDeliveries' => $recentDeliveries,
            'oneTimeSigningSecret' => session('one_time_webhook_signing_secret'),
        ]);
    }

    public function store(StoreWebhookEndpointRequest $request): RedirectResponse
    {
        $company = $request->user()?->firstCompany();
        if ($company === null) {
            abort(403);
        }

        $validated = $request->validated();

        if ($validated['environment'] === 'live' && $company->live_enabled_at === null) {
            return redirect()->back()->withErrors([
                'environment' => __('Live webhook endpoints require Budera admin approval (KYB) first.'),
            ]);
        }

        $plainSecret = Str::random(40);

        $endpoint = WebhookEndpoint::query()->create([
            'company_id' => $company->id,
            'url' => $validated['url'],
            'secret' => $plainSecret,
            'events' => array_values(array_unique($validated['events'])),
            'environment' => $validated['environment'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->auditService->recordDomainAudit([
            'stream' => 'developer',
            'actor_type' => 'user',
            'actor_id' => (string) $request->user()->getKey(),
            'action' => 'webhook_endpoint.created',
            'resource_type' => 'webhook_endpoints',
            'resource_id' => (string) $endpoint->getKey(),
            'environment' => $validated['environment'],
            'metadata' => [
                'company_id' => (string) $company->getKey(),
                'url' => $validated['url'],
            ],
            'correlation_id' => CorrelationId::current($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('company.webhooks.index')
            ->with('status', __('Webhook endpoint created. Copy the signing secret now; it will not be shown again.'))
            ->with('one_time_webhook_signing_secret', $plainSecret);
    }

    public function update(UpdateWebhookEndpointRequest $request, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $company = $request->user()?->firstCompany();
        if ($company === null) {
            abort(403);
        }

        if ((int) $webhookEndpoint->company_id !== (int) $company->id) {
            abort(404);
        }

        $validated = $request->validated();

        if (($validated['environment'] ?? $webhookEndpoint->environment) === 'live' && $company->live_enabled_at === null) {
            return redirect()->back()->withErrors([
                'environment' => __('Live webhook endpoints require Budera admin approval (KYB) first.'),
            ]);
        }

        $webhookEndpoint->fill([
            'url' => $validated['url'] ?? $webhookEndpoint->url,
            'events' => isset($validated['events'])
                ? array_values(array_unique($validated['events']))
                : $webhookEndpoint->events,
            'environment' => $validated['environment'] ?? $webhookEndpoint->environment,
            'is_active' => $validated['is_active'] ?? $webhookEndpoint->is_active,
        ]);
        $webhookEndpoint->save();

        $this->auditService->recordDomainAudit([
            'stream' => 'developer',
            'actor_type' => 'user',
            'actor_id' => (string) $request->user()->getKey(),
            'action' => 'webhook_endpoint.updated',
            'resource_type' => 'webhook_endpoints',
            'resource_id' => (string) $webhookEndpoint->getKey(),
            'environment' => $webhookEndpoint->environment,
            'metadata' => [
                'company_id' => (string) $company->getKey(),
            ],
            'correlation_id' => CorrelationId::current($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('company.webhooks.index')
            ->with('status', __('Webhook endpoint updated.'));
    }

    public function destroy(Request $request, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $company = $request->user()?->firstCompany();
        if ($company === null) {
            abort(403);
        }

        $this->authorize('delete', $webhookEndpoint);

        if ((int) $webhookEndpoint->company_id !== (int) $company->id) {
            abort(404);
        }

        $endpointId = (string) $webhookEndpoint->getKey();
        $environment = (string) $webhookEndpoint->environment;

        $webhookEndpoint->delete();

        $this->auditService->recordDomainAudit([
            'stream' => 'developer',
            'actor_type' => 'user',
            'actor_id' => (string) $request->user()->getKey(),
            'action' => 'webhook_endpoint.deleted',
            'resource_type' => 'webhook_endpoints',
            'resource_id' => $endpointId,
            'environment' => $environment,
            'metadata' => [
                'company_id' => (string) $company->getKey(),
            ],
            'correlation_id' => CorrelationId::current($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('company.webhooks.index')
            ->with('status', __('Webhook endpoint removed.'));
    }

    public function test(
        Request $request,
        WebhookEndpoint $webhookEndpoint,
        WebhookOutboxPayloadFactory $payloadFactory,
    ): RedirectResponse {
        $company = $request->user()?->firstCompany();
        if ($company === null) {
            abort(403);
        }

        $this->authorize('update', $webhookEndpoint);

        if ((int) $webhookEndpoint->company_id !== (int) $company->id) {
            abort(404);
        }

        if (! $webhookEndpoint->is_active) {
            return redirect()->back()->withErrors([
                'test' => __('Activate this endpoint before sending a test.'),
            ]);
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

        ProcessWebhookDeliveryJob::dispatchSync($delivery->getKey(), CorrelationId::current($request));
        $delivery->refresh();

        $this->auditService->recordDomainAudit([
            'stream' => 'developer',
            'actor_type' => 'user',
            'actor_id' => (string) $request->user()->getKey(),
            'action' => 'webhook_endpoint.test_sent',
            'resource_type' => 'webhook_endpoints',
            'resource_id' => (string) $webhookEndpoint->getKey(),
            'environment' => $webhookEndpoint->environment,
            'metadata' => [
                'company_id' => (string) $company->getKey(),
                'delivery_id' => (string) $delivery->getKey(),
                'delivery_status' => (string) $delivery->status,
            ],
            'correlation_id' => CorrelationId::current($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($delivery->status === 'delivered') {
            return redirect()
                ->route('company.webhooks.index')
                ->with('status', __('Test webhook delivered successfully.'));
        }

        return redirect()
            ->route('company.webhooks.index')
            ->withErrors([
                'test' => __('Test failed (HTTP :status). Check the delivery log below.', [
                    'status' => (string) ($delivery->response_status ?? '—'),
                ]),
            ]);
    }
}
