<?php

namespace App\Console\Commands;

use App\Jobs\DispatchWebhookOutboxJob;
use App\Models\WebhookOutbox;
use Illuminate\Console\Command;

class DispatchWebhookOutboxCommand extends Command
{
    protected $signature = 'budera:webhooks:dispatch-outbox {--limit=25 : Max queued outbox rows}';

    protected $description = 'Dispatch queued webhook outbox rows via spatie/laravel-webhook-server';

    public function handle(): int
    {
        $limit = (int) ($this->option('limit') ?? 25);

        $outboxes = WebhookOutbox::query()
            ->where('status', 'queued')
            ->orderBy('id')
            ->limit(max($limit, 1))
            ->get();

        foreach ($outboxes as $outbox) {
            DispatchWebhookOutboxJob::dispatchSync($outbox->id);
        }

        $this->info('Dispatched '.$outboxes->count().' webhook outbox row(s).');

        return self::SUCCESS;
    }
}
