<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\ComplianceFlagController;
use App\Http\Controllers\Admin\KybReviewController;
use App\Http\Controllers\Admin\LiveAccessController;
use App\Http\Controllers\Admin\PartnerBankIntegrationController;
use App\Http\Controllers\BankLinkSessionController;
use App\Http\Controllers\BankPartner\KybDocumentController;
use App\Http\Controllers\BankPartner\ReconciliationController;
use App\Http\Controllers\BankPartner\TransactionController;
use App\Http\Controllers\CompanyApiKeyController;
use App\Http\Controllers\CompanyDashboardEnvironmentController;
use App\Http\Controllers\CompanyInvitationController;
use App\Http\Controllers\CompanyKybSubmissionController;
use App\Http\Controllers\CompanyLiveAccessController;
use App\Http\Controllers\CompanyOAuthClientController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\CompanyTeamController;
use App\Http\Controllers\CompanyWalletController;
use App\Http\Controllers\CompanyWalletPolicyController;
use App\Http\Controllers\CompanyWebhookEndpointController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\KycSessionController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentApprovalController;
use App\Http\Controllers\UserAgentController;
use App\Http\Controllers\UserKycController;
use App\Http\Controllers\UserWalletController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('docs', [DocsController::class, 'index'])->name('docs.index');
Route::get('docs/{page}', [DocsController::class, 'show'])
    ->where('page', '[a-z0-9-]+')
    ->name('docs.show');

Route::middleware(['bank-link.session'])->group(function (): void {
    Route::get('bank-link/{sessionToken}', [BankLinkSessionController::class, 'show'])->name('bank-link.show');
    Route::post('bank-link/{sessionToken}/credentials', [BankLinkSessionController::class, 'storeCredentials'])
        ->name('bank-link.credentials');
    Route::post('bank-link/{sessionToken}/verify', [BankLinkSessionController::class, 'verify'])
        ->name('bank-link.verify');
});

Route::middleware(['kyc.session'])->group(function (): void {
    Route::get('kyc/{sessionToken}', [KycSessionController::class, 'show'])->name('kyc.show');
    Route::post('kyc/{sessionToken}/submit', [KycSessionController::class, 'submit'])->name('kyc.submit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding');
    Route::post('onboarding/company', [OnboardingController::class, 'storeCompany'])->name('onboarding.company.store');
    Route::get('invitations/{token}', [OnboardingController::class, 'acceptInvitation'])->name('invitations.accept');
});

Route::middleware(['auth', 'verified', 'end.user'])->group(function () {
    Route::get('verify-identity', [UserKycController::class, 'show'])->name('user.kyc.show');
    Route::post('verify-identity', [UserKycController::class, 'submit'])->name('user.kyc.submit');

    Route::middleware('end.user.kyc')->group(function () {
        Route::get('my-wallet', UserWalletController::class)->name('user.wallet.index');
        Route::get('my-agents', [UserAgentController::class, 'index'])->name('user.agents.index');
        Route::get('my-agents/{token}', [UserAgentController::class, 'show'])->name('user.agents.show');
    });
});

Route::middleware(['auth', 'verified', 'company.onboarded'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('company/dashboard/environment', CompanyDashboardEnvironmentController::class)
        ->name('company.dashboard.environment');
    Route::get('company/settings', CompanySettingsController::class)->name('company.settings');
    Route::get('company/team', CompanyTeamController::class)->name('company.team');
    Route::post('company/invitations', [CompanyInvitationController::class, 'store'])->name('company.invitations.store');
    Route::delete('company/invitations/{invitation}', [CompanyInvitationController::class, 'destroy'])->name('company.invitations.destroy');

    Route::get('company/oauth-apps', [CompanyOAuthClientController::class, 'index'])->name('company.oauth-apps.index');
    Route::post('company/oauth-apps', [CompanyOAuthClientController::class, 'store'])->name('company.oauth-apps.store');
    Route::delete('company/oauth-apps/{client}', [CompanyOAuthClientController::class, 'destroy'])->name('company.oauth-apps.destroy');

    Route::get('company/api-keys', [CompanyApiKeyController::class, 'index'])->name('company.api-keys.index');
    Route::post('company/api-keys', [CompanyApiKeyController::class, 'store'])->name('company.api-keys.store');
    Route::post('company/api-keys/{apiKey}/rotate', [CompanyApiKeyController::class, 'rotate'])->name('company.api-keys.rotate');
    Route::delete('company/api-keys/{apiKey}', [CompanyApiKeyController::class, 'revoke'])->name('company.api-keys.revoke');

    Route::get('company/webhooks', [CompanyWebhookEndpointController::class, 'index'])->name('company.webhooks.index');
    Route::post('company/webhooks', [CompanyWebhookEndpointController::class, 'store'])->name('company.webhooks.store');
    Route::patch('company/webhooks/{webhookEndpoint}', [CompanyWebhookEndpointController::class, 'update'])->name('company.webhooks.update');
    Route::delete('company/webhooks/{webhookEndpoint}', [CompanyWebhookEndpointController::class, 'destroy'])->name('company.webhooks.destroy');
    Route::post('company/webhooks/{webhookEndpoint}/test', [CompanyWebhookEndpointController::class, 'test'])->name('company.webhooks.test');

    Route::get('company/kyb/submit-for-review', [CompanyKybSubmissionController::class, 'create'])
        ->name('company.kyb.form');
    Route::post('company/kyb/submit-for-review', [CompanyKybSubmissionController::class, 'store'])
        ->name('company.kyb.submit');

    Route::post('company/live-access/request', [CompanyLiveAccessController::class, 'store'])
        ->name('company.live-access.request');

    Route::get('payment-approvals/{token}', [PaymentApprovalController::class, 'show'])->name('payment-approvals.show');
    Route::post('payment-approvals/{token}/approve', [PaymentApprovalController::class, 'approve'])
        ->name('payment-approvals.approve');
    Route::post('payment-approvals/{token}/deny', [PaymentApprovalController::class, 'deny'])
        ->name('payment-approvals.deny');

    Route::get('company/wallets', [CompanyWalletController::class, 'index'])->name('company.wallets.index');
    Route::get('company/wallets/{walletAccount}', [CompanyWalletController::class, 'show'])->name('company.wallets.show');
    Route::get('company/wallets/{walletAccount}/policy', [CompanyWalletPolicyController::class, 'show'])
        ->name('company.wallets.policy.show');
    Route::patch('company/wallets/{walletAccount}/policy', [CompanyWalletPolicyController::class, 'update'])
        ->name('company.wallets.policy.update');
});

Route::middleware(['auth', 'verified', 'budera.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('kyb-reviews', [KybReviewController::class, 'index'])->name('kyb-reviews.index');
    Route::get('kyb-reviews/{kybReview}', [KybReviewController::class, 'show'])->name('kyb-reviews.show');
    Route::post('kyb-reviews/{kybReview}/start-review', [KybReviewController::class, 'startReview'])->name('kyb-reviews.start-review');
    Route::post('kyb-reviews/{kybReview}/approve', [KybReviewController::class, 'approve'])->name('kyb-reviews.approve');
    Route::post('kyb-reviews/{kybReview}/reject', [KybReviewController::class, 'reject'])->name('kyb-reviews.reject');

    Route::get('live-access', [LiveAccessController::class, 'index'])->name('live-access.index');
    Route::post('live-access/{company}/approve', [LiveAccessController::class, 'approve'])->name('live-access.approve');

    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::post('companies/{company}/freeze', [CompanyController::class, 'freeze'])->name('companies.freeze');
    Route::post('companies/{company}/unfreeze', [CompanyController::class, 'unfreeze'])->name('companies.unfreeze');

    Route::get('compliance', [ComplianceFlagController::class, 'index'])->name('compliance.index');
    Route::get('compliance/{complianceFlag}', [ComplianceFlagController::class, 'show'])->name('compliance.show');
    Route::post('compliance/{complianceFlag}/resolve', [ComplianceFlagController::class, 'resolve'])->name('compliance.resolve');

    Route::get('partner-banks', [PartnerBankIntegrationController::class, 'index'])->name('partner-banks.index');
    Route::post('partner-banks', [PartnerBankIntegrationController::class, 'store'])->name('partner-banks.store');
    Route::post('partner-banks/{integration}/test', [PartnerBankIntegrationController::class, 'test'])->name('partner-banks.test');
    Route::patch('partner-banks/{integration}', [PartnerBankIntegrationController::class, 'update'])->name('partner-banks.update');
    Route::delete('partner-banks/{integration}', [PartnerBankIntegrationController::class, 'destroy'])->name('partner-banks.destroy');
});

Route::middleware(['auth', 'verified', 'bank.partner'])->prefix('bank-partner')->name('bank-partner.')->group(function () {
    Route::get('/', App\Http\Controllers\BankPartner\DashboardController::class)->name('dashboard');
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
    Route::get('kyb-documents', [KybDocumentController::class, 'index'])->name('kyb-documents.index');
    Route::get('reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
});

require __DIR__.'/settings.php';
