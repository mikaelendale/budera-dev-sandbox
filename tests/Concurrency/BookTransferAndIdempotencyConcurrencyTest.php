<?php

/**
 * Phase 15.1 concurrency harnesses (see project Phase 15 plan).
 *
 * 15.1a — Two subprocesses call `testing:book-transfer` so both hit the same SQLite file and
 * contend on ledger locks; only one book transfer should complete when the balance allows one debit.
 *
 * 15.1b — Two subprocesses race `testing:idempotency-key-insert` with the same (company_id, key);
 * the unique index matches what `EnsureIdempotency` relies on after cache locks (duplicate insert → 23000).
 *
 * Full double-HTTP parallelism is out of scope for default `php artisan test` (no shared server + :memory: DB).
 *
 * Parallel `php artisan test` workers cannot share the default SQLite :memory: database with
 * subprocesses. These tests switch the default connection to a temp file for the duration of
 * each case so two `artisan` children observe the same rows and exercise real DB locking.
 */
use App\Models\Company;
use App\Models\IdempotencyKey;
use App\Models\Transfer;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'budera_conc_'.uniqid('', true).'.sqlite';
    if (file_exists($path)) {
        unlink($path);
    }
    touch($path);

    config([
        'database.connections.sqlite.database' => $path,
        'database.default' => 'sqlite',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    $this->artisan('migrate', ['--force' => true]);

    $this->concurrencySqlitePath = $path;
});

afterEach(function (): void {
    $path = $this->concurrencySqlitePath ?? null;
    if (is_string($path) && file_exists($path)) {
        @unlink($path);
    }
});

/**
 * @return array<string, string>
 */
function concurrencyProcessEnv(string $sqlitePath): array
{
    return array_merge($_ENV, $_SERVER, [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $sqlitePath,
        'APP_KEY' => (string) config('app.key'),
    ]);
}

test('parallel book transfers: only one completes when balance allows a single debit', function (): void {
    $owner = User::factory()->withCompany('Conc')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $from = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_from_conc',
            'balance_cents' => 0,
        ]);

    $toA = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_to_a',
            'balance_cents' => 0,
        ]);

    $toB = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'mock_to_b',
            'balance_cents' => 0,
        ]);

    app(LedgerService::class)->credit($from, 1_000, 'seed', (string) Str::uuid(), 'Concurrency seed');

    $path = $this->concurrencySqlitePath;
    $env = concurrencyProcessEnv($path);

    $p1 = new Process(
        [PHP_BINARY, base_path('artisan'), 'testing:book-transfer', $from->public_id, $toA->public_id, '600', 'a'],
        base_path(),
        $env,
    );
    $p2 = new Process(
        [PHP_BINARY, base_path('artisan'), 'testing:book-transfer', $from->public_id, $toB->public_id, '600', 'b'],
        base_path(),
        $env,
    );

    $p1->start();
    $p2->start();
    $p1->wait();
    $p2->wait();

    $codes = [$p1->getExitCode(), $p2->getExitCode()];
    expect(in_array(0, $codes, true))->toBeTrue()
        ->and(in_array(1, $codes, true))->toBeTrue();

    $completed = Transfer::query()
        ->withoutGlobalScopes()
        ->where('from_wallet_account_id', $from->id)
        ->where('status', 'completed')
        ->count();

    expect($completed)->toBe(1);

    $from->refresh();
    expect((int) $from->balance_cents)->toBe(400);
});

test('parallel idempotency key inserts: unique company_id and key yields one row', function (): void {
    $owner = User::factory()->withCompany('Idem')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $path = $this->concurrencySqlitePath;
    $env = concurrencyProcessEnv($path);

    $sharedKey = 'idem_race_shared_key';
    $p1 = new Process(
        [PHP_BINARY, base_path('artisan'), 'testing:idempotency-key-insert', (string) $company->id, $sharedKey, 'mat_a'],
        base_path(),
        $env,
    );
    $p2 = new Process(
        [PHP_BINARY, base_path('artisan'), 'testing:idempotency-key-insert', (string) $company->id, $sharedKey, 'mat_b'],
        base_path(),
        $env,
    );

    $p1->start();
    $p2->start();
    $p1->wait();
    $p2->wait();

    $exitCodes = [$p1->getExitCode(), $p2->getExitCode()];
    sort($exitCodes);
    expect($exitCodes)->toBe([0, 2]);

    expect(
        IdempotencyKey::query()->where('company_id', $company->id)->where('key', $sharedKey)->count()
    )->toBe(1);
});
