<?php

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Support\Str;

test('company a api key cannot load company b payment by public id', function (): void {
    $ownerA = User::factory()->withCompany('Alpha')->create();
    $companyA = Company::query()->where('owner_id', $ownerA->id)->firstOrFail();

    $ownerB = User::factory()->withCompany('Beta')->create();
    $companyB = Company::query()->where('owner_id', $ownerB->id)->firstOrFail();

    $walletB = WalletAccount::factory()->create(['company_id' => $companyB->id, 'user_id' => $ownerB->id]);
    $paymentB = Payment::factory()->create(['wallet_account_id' => $walletB->id]);

    $plainA = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $companyA->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plainA),
        'abilities' => ['wallet:read'],
        'metadata' => ['key_last4' => substr($plainA, -4)],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainA)
        ->getJson('/api/v1/payments/'.$paymentB->public_id)
        ->assertNotFound();
});
