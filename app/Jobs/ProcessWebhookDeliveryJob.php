<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\WebhookServer\Signer\Signer;
use Throwable;

class ProcessWebhookDeliveryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 30, 120, 600, 3600];

    public function __construct(
        public readonly int $deliveryId,
        public readonly ?string $correlationId = null,
    ) {
        $this->onQueue((string) config('budera.queues.webhooks'));
    }

    public function handle(): void
    {
        if ($this->correlationId !== null && $this->correlationId !== '') {
            Log::shareContext(['correlation_id' => $this->correlationId]);
        }

        $delivery = WebhookDelivery::query()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        if (! $delivery->isEligibleForDispatch()) {
            return;
        }

        $endpoint = WebhookEndpoint::query()
            ->withoutCompanyScope()
            ->whereKey($delivery->webhook_endpoint_id)
            ->first();

        if ($endpoint === null || ! $endpoint->is_active) {
            $delivery->forceFill([
                'status' => 'failed',
                'response_body' => 'Webhook endpoint missing or inactive',
            ])->save();

            return;
        }

        $delivery->attempts = (int) $delivery->attempts + 1;
        $delivery->last_attempted_at = now();
        $delivery->save();

        $payload = is_array($delivery->payload) ? $delivery->payload : [];

        try {
            /** @var Signer $signer */
            $signer = app(config('webhook-server.signer'));
            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
            $signature = $signer->calculateSignature($endpoint->url, $payload, $endpoint->secret);
            $headers = array_merge(config('webhook-server.headers'), [
                $signer->signatureHeaderName() => $signature,
            ]);

            $response = Http::withHeaders($headers)
                ->withBody($payloadJson, 'application/json')
                ->timeout((int) config('webhook-server.timeout_in_seconds'))
                ->withOptions(['verify' => (bool) config('webhook-server.verify_ssl')])
                ->post($endpoint->url);

            $delivery->forceFill([
                'response_status' => $response->status(),
                'response_body' => Str::limit($response->body(), 2000),
            ])->save();

            if ($response->successful()) {
                $delivery->forceFill(['status' => 'delivered'])->save();

                return;
            }
        } catch (Throwable $e) {
            $delivery->forceFill([
                'response_body' => Str::limit($e->getMessage(), 2000),
            ])->save();
        }

        if ((int) $delivery->attempts >= 5) {
            $delivery->forceFill(['status' => 'failed'])->save();

            return;
        }

        throw new RuntimeException('Webhook delivery HTTP failure; retrying with backoff.');
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);

        if ($delivery === null || $delivery->status !== 'queued') {
            return;
        }

        $delivery->forceFill([
            'status' => 'failed',
            'response_body' => Str::limit($exception?->getMessage() ?? 'Job failed', 2000),
        ])->save();
    }
}
