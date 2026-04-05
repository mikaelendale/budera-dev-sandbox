<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\SpendControls\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $approvalRequest = ApprovalRequest::query()->where('approval_token', $token)->first();
        if ($approvalRequest === null) {
            abort(404);
        }

        $payment = $this->paymentFromApproval($approvalRequest);
        if ($payment === null) {
            abort(404);
        }

        $wallet = $this->walletForPayment($payment);

        $this->assertCanAccessApproval($user, $payment, requireManage: false);

        return Inertia::render('payment-approvals/show', [
            'token' => $token,
            'approvalStatus' => (string) $approvalRequest->status,
            'expiresAt' => $approvalRequest->expires_at?->toIso8601String(),
            'payment' => [
                'public_id' => $payment->public_id,
                'amount_usd' => $payment->amount_usd !== null ? (string) $payment->amount_usd : null,
                'payee_ref' => $payment->payee_ref,
                'wallet_public_id' => $wallet?->public_id,
            ],
            'canDecide' => $this->userCanDecideApproval($user, $payment),
        ]);
    }

    public function approve(Request $request, string $token): RedirectResponse
    {
        return $this->decide($request, $token, approve: true);
    }

    public function deny(Request $request, string $token): RedirectResponse
    {
        return $this->decide($request, $token, approve: false);
    }

    private function decide(Request $request, string $token, bool $approve): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $approvalRequest = ApprovalRequest::query()->where('approval_token', $token)->first();
        if ($approvalRequest === null) {
            abort(404);
        }

        $payment = $this->paymentFromApproval($approvalRequest);
        if ($payment === null) {
            abort(404);
        }

        $this->assertCanAccessApproval($user, $payment, requireManage: true);

        $ok = $approve ? $this->approvalService->approve($token) : $this->approvalService->deny($token);

        return redirect()
            ->route('payment-approvals.show', ['token' => $token])
            ->with(
                $ok ? 'status' : 'error',
                $ok
                    ? ($approve ? __('Payment approved.') : __('Payment denied.'))
                    : __('This approval link is no longer valid or has already been used.'),
            );
    }

    private function paymentFromApproval(ApprovalRequest $approvalRequest): ?Payment
    {
        if ($approvalRequest->approvable_type !== Payment::class) {
            return null;
        }

        return Payment::query()->withoutGlobalScopes()->find($approvalRequest->approvable_id);
    }

    private function walletForPayment(Payment $payment): ?WalletAccount
    {
        return WalletAccount::query()->withoutGlobalScopes()->whereKey($payment->wallet_account_id)->first();
    }

    private function userCanDecideApproval(User $user, Payment $payment): bool
    {
        if ($user->is_budera_admin) {
            return true;
        }

        $wallet = $this->walletForPayment($payment);
        if ($wallet === null) {
            return false;
        }

        $company = $user->firstCompany();
        if ($company === null || (int) $wallet->company_id !== (int) $company->getKey()) {
            return false;
        }

        return $user->hasCompanyPermission($company, 'company.wallets.manage');
    }

    private function assertCanAccessApproval(User $user, Payment $payment, bool $requireManage): void
    {
        if ($user->is_budera_admin) {
            return;
        }

        $wallet = $this->walletForPayment($payment);
        if ($wallet === null) {
            abort(403);
        }

        $company = $user->firstCompany();
        if ($company === null || (int) $wallet->company_id !== (int) $company->getKey()) {
            abort(403);
        }

        $permission = $requireManage ? 'company.wallets.manage' : 'company.wallets.view';

        if (! $user->hasCompanyPermission($company, $permission)) {
            abort(403);
        }
    }
}
