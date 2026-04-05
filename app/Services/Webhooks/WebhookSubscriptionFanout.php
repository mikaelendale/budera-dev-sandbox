<?php

namespace App\Services\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookOutbox;
use Illuminate\Support\Facades\DB;

class WebhookSubscriptionFanout
{
    public function fanout(WebhookOutbox $outbox): void
    {
        if ($outbox->company_id === null) {
            return;
        }

        $environment = $outbox->environment;

        $endpoints = WebhookEndpoint::query()
            ->where('company_id', $outbox->company_id)
            ->where('is_active', true)
            ->when(
                $environment !== null,
                fn ($q) => $q->where('environment', $environment),
                fn ($q) => $q->whereNull('environment'),
            )
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => $this->endpointSubscribesToEvent($endpoint, $outbox->event));

        if ($endpoints->isEmpty()) {
            return;
        }

        $payload = is_array($outbox->payload) ? $outbox->payload : [];

        DB::transaction(function () use ($outbox, $endpoints, $payload): void {
            foreach ($endpoints as $endpoint) {
                WebhookDelivery::query()->create([
                    'webhook_outbox_id' => $outbox->getKey(),
                    'webhook_endpoint_id' => $endpoint->getKey(),
                    'event' => $outbox->event,
                    'event_id' => $outbox->event_id,
                    'payload' => $payload,
                    'status' => 'queued',
                    'attempts' => 0,
                    'last_attempted_at' => null,
                    'response_status' => null,
                    'response_body' => null,
                ]);
            }
        });
    }

    private function endpointSubscribesToEvent(WebhookEndpoint $endpoint, string $event): bool
    {
        $events = is_array($endpoint->events) ? $endpoint->events : [];

        if (in_array('*', $events, true)) {
            return true;
        }

        return in_array($event, $events, true);
    }
}
