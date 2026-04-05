<?php

use App\Models\ApiKey;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Topup;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function sandboxErrorsApiKey(User $owner, array $abilities): string
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

test('simulation settlement returns payment_not_processing when payment is not processing', function (): void {
    config(['services.mock_bank.base_url' => 'http://mock-bank.test']);

    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_err',
        ]);

    Payment::factory()
        ->settled()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'metadata' => ['bank_transfer_id' => 'trf_not_proc'],
        ]);

    $plain = sandboxErrorsApiKey($owner, ['wallet:read', 'wallet:pay', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_not_proc'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'payment_not_processing');
});

test('simulation settlement returns topup_not_processing when topup is not processing', function (): void {
    config(['services.mock_bank.base_url' => 'http://mock-bank.test']);

    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_top_np',
        ]);

    Topup::factory()
        ->settled()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'metadata' => ['bank_transfer_id' => 'trf_top_np'],
        ]);

    $plain = sandboxErrorsApiKey($owner, ['wallet:read', 'wallet:topup', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_top_np'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'topup_not_processing');
});

test('simulation settlement returns resource_not_found when transfer id matches nothing', function (): void {
    config(['services.mock_bank.base_url' => 'http://mock-bank.test']);

    $owner = User::factory()->withCompany()->create();
    $plain = sandboxErrorsApiKey($owner, ['wallet:read', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_missing_xyz'])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'resource_not_found');
});

test('simulation settlement returns mock_bank_control_failed when mock bank control errors', function (): void {
    config(['services.mock_bank.base_url' => 'http://mock-bank.test']);

    Http::fake([
        'http://mock-bank.test/api/control/settle-now' => Http::response('', 404),
    ]);

    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
            'partner_account_id' => 'acct_mb_fail',
        ]);

    Payment::factory()
        ->processing()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'metadata' => ['bank_transfer_id' => 'trf_mb_fail'],
        ]);

    $plain = sandboxErrorsApiKey($owner, ['wallet:read', 'wallet:pay', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/settlement', ['bank_transfer_id' => 'trf_mb_fail'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'mock_bank_control_failed');
});

test('simulation payment return returns payment_not_found', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = sandboxErrorsApiKey($owner, ['wallet:read', 'wallet:pay', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/return', ['bank_transfer_id' => 'trf_no_pay'])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'payment_not_found');
});

test('simulation payment return returns payment_not_settled when still processing', function (): void {
    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
        ]);

    Payment::factory()
        ->processing()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'metadata' => ['bank_transfer_id' => 'trf_ns'],
        ]);

    $plain = sandboxErrorsApiKey($owner, ['wallet:read', 'wallet:pay', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/return', ['bank_transfer_id' => 'trf_ns'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'payment_not_settled');
});

test('simulation payment return returns payment_missing_settlement_ledger when metadata incomplete', function (): void {
    $owner = User::factory()->withCompany()->create();
    $companyId = (int) Company::query()->where('owner_id', $owner->id)->value('id');

    $wallet = WalletAccount::factory()
        ->active()
        ->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'environment' => 'sandbox',
        ]);

    Payment::factory()
        ->settled()
        ->create([
            'wallet_account_id' => $wallet->id,
            'environment' => 'sandbox',
            'metadata' => ['bank_transfer_id' => 'trf_no_led'],
        ]);

    $plain = sandboxErrorsApiKey($owner, ['wallet:read', 'wallet:pay', 'sandbox:simulate']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/return', ['bank_transfer_id' => 'trf_no_led'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'payment_missing_settlement_ledger');
});

test('simulation microdeposit returns bank_link_not_awaiting_microdeposit when link is verified', function (): void {
    $owner = User::factory()->withCompany()->create();
    $plain = sandboxErrorsApiKey($owner, ['wallet:read', 'wallet:link', 'sandbox:simulate']);

    $link = BankLink::factory()
        ->verified()
        ->create([
            'user_id' => $owner->id,
            'environment' => 'sandbox',
        ]);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/sandbox/simulate/microdeposit', ['bank_link_id' => $link->public_id])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'bank_link_not_awaiting_microdeposit');
});
