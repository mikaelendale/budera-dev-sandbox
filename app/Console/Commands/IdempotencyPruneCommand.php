<?php

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('idempotency:prune')]
#[Description('Delete idempotency key cache rows older than 24 hours')]
class IdempotencyPruneCommand extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subHours(24);

        $deleted = IdempotencyKey::query()
            ->withoutCompanyScope()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} idempotency key row(s).");

        return self::SUCCESS;
    }
}
