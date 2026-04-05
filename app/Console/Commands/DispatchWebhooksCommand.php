<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWebhookDeliveryJob;
use App\Models\WebhookDelivery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('webhooks:dispatch {--limit=50 : Max webhook deliveries to attempt in this run}')]
#[Description('Dispatch queued webhook deliveries to subscriber endpoints (HMAC-signed POSTs)')]
class DispatchWebhooksCommand extends Command
{
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $candidates = WebhookDelivery::query()
            ->where('status', 'queued')
            ->where('attempts', '<', 5)
            ->orderBy('id')
            ->limit($limit * 5)
            ->get();

        $eligible = $candidates->filter(fn (WebhookDelivery $d) => $d->isEligibleForDispatch())->take($limit);

        foreach ($eligible as $delivery) {
            ProcessWebhookDeliveryJob::dispatch($delivery->getKey());
        }

        $this->info('Processed '.$eligible->count().' webhook delivery attempt(s).');

        return self::SUCCESS;
    }
}
