<?php

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Str;

test('stored api key is sha256 hex and not equal to plaintext', function (): void {
    $owner = User::factory()->withCompany('HashCo')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $plain = 'sk_sandbox_'.Str::random(42);
    $key = ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read'],
        'metadata' => [],
    ]);

    $key->refresh();

    expect($key->key_hash)->toBe(hash('sha256', $plain))
        ->and($key->key_hash)->not->toBe($plain)
        ->and(strlen((string) $key->key_hash))->toBe(64)
        ->and((bool) preg_match('/^[a-f0-9]{64}$/', (string) $key->key_hash))->toBeTrue();
});
