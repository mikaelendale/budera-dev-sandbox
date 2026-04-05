<?php

namespace App\Services\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookOutbox;
use Illuminate\Support\Arr;

class WebhookFanOutService
{
    public function __construct(
        private readonly WebhookOutboxPayloadFactory $payloadFactory,
    ) {}

    /**
     * Create queued webhook_deliveries for each subscribed company endpoint.
     */
    public function fanOut(WebhookOutbox $outbox): void
    {
        $companyId = $outbox->company_id;
        $environment = is_string($outbox->environment) && $outbox->environment !== ''
            ? $outbox->environment
            : null;

        if ($companyId === null || $environment === null) {
            return;
        }

        $event = $outbox->event;
        $payload = is_array($outbox->payload) ? $outbox->payload : [];
        $normalizedPayload = $this->payloadFactory->forEvent($event, $payload);

        $endpoints = WebhookEndpoint::query()
            ->withoutCompanyScope()
            ->where('company_id', $companyId)
            ->where('environment', $environment)
            ->where('is_active', true)
            ->get();

        foreach ($endpoints as $endpoint) {
            if (! $this->endpointSubscribesToEvent($endpoint->events, $event)) {
                continue;
            }

            WebhookDelivery::query()->create([
                'webhook_outbox_id' => $outbox->getKey(),
                'webhook_endpoint_id' => $endpoint->getKey(),
                'event' => $event,
                'event_id' => $outbox->event_id,
                'payload' => $normalizedPayload,
                'status' => 'queued',
            ]);
        }
    }

    /**
     * @param  array<int, mixed>|null  $events
     */
    private function endpointSubscribesToEvent(?array $events, string $event): bool
    {
        $events = Arr::wrap($events);

        foreach ($events as $subscribed) {
            if (! is_string($subscribed)) {
                continue;
            }

            if ($subscribed === '*' || $subscribed === $event) {
                return true;
            }
        }

        return false;
    }
}
