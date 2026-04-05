<?php

use App\Models\ApiKey;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Str;

function bankLinkErrorsApiKey(User $owner, array $abilities): string
{
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => $abilities,
        'metadata' => [],
    ]);

    return $plain;
}

test('bank link store returns invalid_request when neither credentials nor hosted fields are provided', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = bankLinkErrorsApiKey($owner, ['wallet:link']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_request');
});

test('bank link store returns invalid_request when credentials and hosted fields are mixed', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = bankLinkErrorsApiKey($owner, ['wallet:link']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'routing_number' => '123456789',
            'account_number' => '1234567890123',
            'end_user_email' => 'someone@example.com',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_request');
});

test('bank link store returns end_user_not_found for unknown hosted email', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = bankLinkErrorsApiKey($owner, ['wallet:link']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'end_user_email' => 'nobody-at-all@example.com',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'end_user_not_found');
});

test('bank link store validation rejects invalid routing number shape', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = bankLinkErrorsApiKey($owner, ['wallet:link']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'routing_number' => '12345',
            'account_number' => '1234567890123',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['routing_number']);
});

test('bank link store validation rejects short account number', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = bankLinkErrorsApiKey($owner, ['wallet:link']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'routing_number' => '123456789',
            'account_number' => '123',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_number']);
});

test('bank link verify returns microdeposit_verification_failed on wrong amounts', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = bankLinkErrorsApiKey($owner, ['wallet:link']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'routing_number' => '123456789',
            'account_number' => '1234567890123',
        ])->assertCreated();

    $link = BankLink::query()->where('user_id', $owner->id)->latest('id')->firstOrFail();

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson("/api/v1/bank-links/{$link->public_id}/verify", [
            'amount_first_cents' => 99,
            'amount_second_cents' => 99,
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'microdeposit_verification_failed');
});

test('bank link verify returns bank_link_not_awaiting_verification when already verified', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = bankLinkErrorsApiKey($owner, ['wallet:link']);

    $link = BankLink::factory()
        ->verified()
        ->create([
            'user_id' => $owner->id,
            'environment' => 'sandbox',
        ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson("/api/v1/bank-links/{$link->public_id}/verify", [
            'amount_first_cents' => 12,
            'amount_second_cents' => 34,
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'bank_link_not_awaiting_verification');
});

test('bank link destroy returns bank_link_cannot_revoke from initiated state', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = bankLinkErrorsApiKey($owner, ['wallet:link']);

    $link = BankLink::factory()
        ->create([
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'status' => 'initiated',
        ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->deleteJson("/api/v1/bank-links/{$link->public_id}")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'bank_link_cannot_revoke');
});
