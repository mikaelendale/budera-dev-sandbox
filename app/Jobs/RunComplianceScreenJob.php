<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\SpendControls\ComplianceScreen;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunComplianceScreenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $paymentId,
        private readonly ?string $correlationId = null,
    ) {
        $this->onQueue((string) config('budera.queues.compliance'));
    }

    public function handle(ComplianceScreen $screen): void
    {
        if ($this->correlationId !== null && $this->correlationId !== '') {
            Log::shareContext(['correlation_id' => $this->correlationId]);
        }

        $payment = Payment::query()->find($this->paymentId);

        if (! $payment instanceof Payment) {
            return;
        }

        $screen->run($payment);
    }
}
