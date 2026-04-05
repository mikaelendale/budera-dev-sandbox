<?php

namespace App\Jobs;

use App\Models\WebhookOutbox;
use App\Services\Webhooks\WebhookOutboxPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\WebhookServer\WebhookCall;
use Throwable;

class DispatchWebhookOutboxJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $outboxId,
        public readonly ?string $correlationId = null,
    ) {
        $this->onQueue((string) config('budera.queues.webhooks'));
    }

    public function handle(WebhookOutboxPayloadFactory $payloadFactory): void
    {
        if ($this->correlationId !== null && $this->correlationId !== '') {
            Log::shareContext(['correlation_id' => $this->correlationId]);
        }

        $outbox = WebhookOutbox::query()->find($this->outboxId);
        if ($outbox === null) {
            return;
        }

        if ($outbox->status !== 'queued') {
            return;
        }

        $outbox->reserved_at = now();
        $outbox->attempts = (int) $outbox->attempts + 1;
        $outbox->last_error = null;
        $outbox->save();

        try {
            $destinationUrl = is_string($outbox->destination_url) && $outbox->destination_url !== ''
                ? (string) $outbox->destination_url
                : null;

            $destinationSecret = is_string($outbox->destination_key) && $outbox->destination_key !== ''
                ? (string) $outbox->destination_key
                : null;

            if ($destinationUrl === null || $destinationSecret === null) {
                $outbox->status = 'routed';
                $outbox->reserved_at = null;
                $outbox->save();

                return;
            }

            $payload = is_array($outbox->payload) ? $outbox->payload : [];
            $normalizedPayload = $payloadFactory->forEvent($outbox->event, $payload);

            WebhookCall::create()
                ->url($destinationUrl)
                ->payload($normalizedPayload)
                ->useSecret($destinationSecret)
                ->dispatchSync();

            $outbox->status = 'dispatched';
            $outbox->reserved_at = null;
            $outbox->save();
        } catch (Throwable $e) {
            $outbox->status = 'failed';
            $outbox->reserved_at = null;
            $outbox->last_error = (string) Str::limit($e->getMessage(), 2000);
            $outbox->save();
        }
    }
}
