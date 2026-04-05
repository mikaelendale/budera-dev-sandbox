<?php

use App\Http\Controllers\Api\MockBankWebhookController;
use App\Http\Controllers\Api\V1\ApprovalDecisionController;
use App\Http\Controllers\Api\V1\BankLinkController;
use App\Http\Controllers\Api\V1\CompanyWebhookEndpointTestController;
use App\Http\Controllers\Api\V1\LedgerController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\Sandbox\SimulationController;
use App\Http\Controllers\Api\V1\SandboxKycApproveController;
use App\Http\Controllers\Api\V1\TopupController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\WalletAccountController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/mock-bank', [MockBankWebhookController::class, 'handle'])
    ->middleware('throttle:120,1');

Route::prefix('v1')->middleware(['auth:api-key,api', 'throttle:api-company'])->group(function (): void {
    Route::post('approvals/{token}/approve', [ApprovalDecisionController::class, 'approve'])
        ->middleware('api-key.abilities:wallet:approve');
    Route::post('approvals/{token}/deny', [ApprovalDecisionController::class, 'deny'])
        ->middleware('api-key.abilities:wallet:approve');
    Route::get('wallet/me', [WalletController::class, 'me'])
        ->middleware('api-key.abilities:wallet:read');

    Route::post('company/webhooks/{webhookEndpoint}/test', CompanyWebhookEndpointTestController::class)
        ->middleware('api-key.abilities:webhooks:manage');

    Route::post('wallet/accounts', [WalletAccountController::class, 'store'])
        ->middleware('api-key.abilities:wallet:pay');
    Route::get('wallet/accounts/{walletAccount}', [WalletAccountController::class, 'show'])
        ->middleware('api-key.abilities:wallet:read');
    Route::post('wallet/accounts/{walletAccount}/kyc', [WalletAccountController::class, 'submitKyc'])
        ->middleware('api-key.abilities:wallet:pay');

    Route::post('sandbox/kyc/approve/{walletKycVerification}', SandboxKycApproveController::class)
        ->middleware(['sandbox.kyc', 'api-key.abilities:wallet:pay']);

    Route::prefix('sandbox/simulate')->middleware(['sandbox.environment', 'api-key.abilities:sandbox:simulate'])->group(function (): void {
        Route::post('settlement', [SimulationController::class, 'settlement']);
        Route::post('return', [SimulationController::class, 'paymentReturn']);
        Route::post('kyc-approve', [SimulationController::class, 'kycApprove']);
        Route::post('microdeposit', [SimulationController::class, 'microdeposit']);
    });

    Route::get('payments', [PaymentController::class, 'index'])
        ->middleware('api-key.abilities:wallet:read');
    Route::post('payments', [PaymentController::class, 'store'])
        ->middleware(['api-key.abilities:wallet:pay', 'idempotency']);
    Route::get('payments/{payment}', [PaymentController::class, 'show'])
        ->middleware('api-key.abilities:wallet:read');

    Route::post('bank-links', [BankLinkController::class, 'store'])
        ->middleware('api-key.abilities:wallet:link');
    Route::get('bank-links/{bankLink}', [BankLinkController::class, 'show'])
        ->middleware('api-key.abilities:wallet:read');
    Route::post('bank-links/{bankLink}/verify', [BankLinkController::class, 'verify'])
        ->middleware('api-key.abilities:wallet:link');
    Route::delete('bank-links/{bankLink}', [BankLinkController::class, 'destroy'])
        ->middleware('api-key.abilities:wallet:link');

    Route::get('topups', [TopupController::class, 'index'])
        ->middleware('api-key.abilities:wallet:read');
    Route::post('topups', [TopupController::class, 'store'])
        ->middleware(['api-key.abilities:wallet:topup', 'idempotency']);
    Route::get('topups/{topup}', [TopupController::class, 'show'])
        ->middleware('api-key.abilities:wallet:read');

    Route::get('transfers', [TransferController::class, 'index'])
        ->middleware('api-key.abilities:wallet:read');
    Route::post('transfers', [TransferController::class, 'store'])
        ->middleware(['api-key.abilities:wallet:transfer', 'idempotency']);
    Route::get('transfers/{transfer}', [TransferController::class, 'show'])
        ->middleware('api-key.abilities:wallet:read');

    Route::get('wallets/{walletAccount}/ledger', [LedgerController::class, 'index'])
        ->middleware('api-key.abilities:wallet:read');
});
