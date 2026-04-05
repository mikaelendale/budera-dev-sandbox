<?php

use App\Models\ApiKey;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\KybReview;
use App\Models\Payment;
use App\Models\Topup;
use App\Models\Transfer;
use App\Models\User;
use App\Models\WalletAccount;
use App\Tenancy\CompanyContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('belongs to company scope filters models by company context', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $apiKeyA = ApiKey::factory()->create(['company_id' => $companyA->id]);
    $apiKeyB = ApiKey::factory()->create(['company_id' => $companyB->id]);
    $kybA = KybReview::factory()->create(['company_id' => $companyA->id]);
    $kybB = KybReview::factory()->create(['company_id' => $companyB->id]);
    $walletA = WalletAccount::factory()->create(['company_id' => $companyA->id]);
    $walletB = WalletAccount::factory()->create(['company_id' => $companyB->id]);
    $paymentA = Payment::factory()->create(['wallet_account_id' => $walletA->id]);
    $paymentB = Payment::factory()->create(['wallet_account_id' => $walletB->id]);
    $topupA = Topup::factory()->create(['wallet_account_id' => $walletA->id]);
    $topupB = Topup::factory()->create(['wallet_account_id' => $walletB->id]);
    $transferA = Transfer::factory()->create([
        'from_wallet_account_id' => $walletA->id,
        'to_wallet_account_id' => $walletB->id,
    ]);
    $transferB = Transfer::factory()->create([
        'from_wallet_account_id' => $walletB->id,
        'to_wallet_account_id' => $walletA->id,
    ]);

    app()->instance(CompanyContext::class, new CompanyContext(companyId: $companyA->id));

    expect(ApiKey::query()->pluck('id')->all())->toBe([$apiKeyA->id]);
    expect(KybReview::query()->pluck('id')->all())->toBe([$kybA->id]);
    expect(Payment::query()->pluck('id')->all())->toBe([$paymentA->id]);
    expect(Topup::query()->pluck('id')->all())->toBe([$topupA->id]);

    $transferIds = Transfer::query()->pluck('id')->sort()->values()->all();
    expect($transferIds)->toBe(collect([$transferA->id, $transferB->id])->sort()->values()->all());

    expect(ApiKey::query()->withoutCompanyScope()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$apiKeyA->id, $apiKeyB->id])->sort()->values()->all());
    expect(KybReview::query()->withoutCompanyScope()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$kybA->id, $kybB->id])->sort()->values()->all());
});

test('company scope is bypassed for admin contexts', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    ApiKey::factory()->create(['company_id' => $companyA->id]);
    ApiKey::factory()->create(['company_id' => $companyB->id]);

    app()->instance(CompanyContext::class, new CompanyContext(
        companyId: null,
        bypassCompanyScope: true,
    ));

    expect(ApiKey::query()->count())->toBe(2);
});

test('bank links are scoped by company membership', function () {
    $this->seed(RoleSeeder::class);

    $memberA = User::factory()->withCompany('Company A')->create();
    $memberB = User::factory()->withCompany('Company B')->create();
    $companyA = $memberA->firstCompany();

    expect($companyA)->not()->toBeNull();

    $bankLinkA = BankLink::factory()->create(['user_id' => $memberA->id]);
    BankLink::factory()->create(['user_id' => $memberB->id]);

    app()->instance(CompanyContext::class, new CompanyContext(companyId: $companyA?->id));

    expect(BankLink::query()->pluck('id')->all())->toBe([$bankLinkA->id]);
});
