<?php

use App\Models\Company;
use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\Artisan;

test('idempotency prune deletes rows older than 24 hours', function (): void {
    $company = Company::factory()->create();

    IdempotencyKey::query()->create([
        'key' => 'stale-key',
        'company_id' => $company->id,
        'request_hash' => hash('sha256', 'a'),
        'response_status' => 201,
        'response_body' => ['stale' => true],
        'created_at' => now()->subHours(25),
    ]);

    IdempotencyKey::query()->create([
        'key' => 'fresh-key',
        'company_id' => $company->id,
        'request_hash' => hash('sha256', 'b'),
        'response_status' => 201,
        'response_body' => ['fresh' => true],
        'created_at' => now()->subHour(),
    ]);

    Artisan::call('idempotency:prune');

    expect(IdempotencyKey::query()->withoutCompanyScope()->count())->toBe(1);

    $remaining = IdempotencyKey::query()->withoutCompanyScope()->firstOrFail();
    expect($remaining->key)->toBe('fresh-key');
});
