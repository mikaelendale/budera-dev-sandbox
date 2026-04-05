<?php

use App\Models\IdempotencyKey;
use App\Models\WalletAccount;
use App\Services\TransferService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('budera:reconcile', function (): int {
    return $this->call('ledger:reconcile');
})->purpose('Alias for ledger:reconcile (daily wallet vs ledger check)');

Schedule::command('ledger:reconcile')->daily();
Schedule::command('idempotency:prune')->daily();
Schedule::command('webhooks:dispatch')->everyMinute();

if (app()->environment('testing')) {
    Artisan::command('testing:book-transfer {fromPublicId} {toPublicId} {amountCents} {seed}', function (
        string $fromPublicId,
        string $toPublicId,
        string $amountCents,
        string $seed,
    ): int {
        /** @var WalletAccount $from */
        $from = WalletAccount::query()->where('public_id', $fromPublicId)->firstOrFail();
        /** @var WalletAccount $to */
        $to = WalletAccount::query()->where('public_id', $toPublicId)->firstOrFail();

        try {
            app(TransferService::class)->createBookTransfer(
                $from,
                $to,
                (int) $amountCents,
                'testing_bt_'.$seed,
            );
        } catch (Throwable) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    })->purpose('Testing-only: run one book transfer (concurrency harness)');

    Artisan::command('testing:idempotency-key-insert {companyId} {key} {hashMaterial}', function (
        string $companyId,
        string $key,
        string $hashMaterial,
    ): int {
        try {
            IdempotencyKey::query()->create([
                'company_id' => (int) $companyId,
                'key' => $key,
                'request_hash' => hash('sha256', $hashMaterial),
                'response_status' => 201,
                'response_body' => ['ok' => true],
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? '') === '23000') {
                return 2;
            }

            throw $e;
        }

        return self::SUCCESS;
    })->purpose('Testing-only: insert idempotency row (unique constraint harness)');
}
