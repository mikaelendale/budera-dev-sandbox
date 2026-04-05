<?php

namespace App\Http\Controllers;

use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use App\States\WalletAccount\WalletAccountActive;
use App\States\WalletKycVerification\WalletKycVerificationApproved;
use App\States\WalletKycVerification\WalletKycVerificationNotStarted;
use App\States\WalletKycVerification\WalletKycVerificationPending;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserKycController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isEndUser()) {
            abort(403);
        }

        if ($user->isKycVerified()) {
            return redirect()->route('user.wallet.index');
        }

        $wallet = $this->resolveWallet($user->getKey());
        $verification = $wallet !== null
            ? $this->ensureKycVerification($wallet)
            : null;

        if ($wallet !== null && $verification !== null && $verification->status instanceof WalletKycVerificationPending) {
            $this->completeSandboxKycIfEligible($wallet, $verification);
            $verification->refresh();
        }

        $step = match (true) {
            $verification === null => 'identity',
            $verification->status instanceof WalletKycVerificationApproved => 'approved',
            $verification->status instanceof WalletKycVerificationPending => 'verifying',
            default => 'identity',
        };

        return Inertia::render('user/verify-identity', [
            'step' => $step,
            'walletPublicId' => $wallet?->public_id,
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isEndUser()) {
            abort(403);
        }

        if ($user->isKycVerified()) {
            return redirect()->route('user.wallet.index');
        }

        $request->validate([
            'legal_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'address_line_1' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:2',
            'zip' => 'required|string|max:10',
            'ssn_last4' => 'required|string|size:4',
        ]);

        $wallet = $this->resolveWallet($user->getKey());

        if ($wallet === null) {
            return redirect()->route('user.kyc.show')
                ->with('error', 'No wallet found. Please contact support.');
        }

        $verification = $this->ensureKycVerification($wallet);

        if (! $verification->status instanceof WalletKycVerificationNotStarted) {
            return redirect()->route('user.kyc.show')
                ->with('error', 'Verification has already been submitted.');
        }

        $verification->submitted_payload = $request->only([
            'legal_name', 'date_of_birth', 'address_line_1', 'city', 'state', 'zip', 'ssn_last4',
        ]);
        $verification->status->transitionTo(WalletKycVerificationPending::class);
        $verification->save();

        $this->completeSandboxKycIfEligible($wallet, $verification);

        return redirect()->route('user.kyc.show');
    }

    private function resolveWallet(int|string $userId): ?WalletAccount
    {
        return WalletAccount::query()
            ->withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereNull('company_id')
            ->first();
    }

    /**
     * Personal wallets created before KYC provisioning may lack a row; create one so the flow works.
     */
    private function ensureKycVerification(WalletAccount $wallet): WalletKycVerification
    {
        $verification = $wallet->kycVerifications()->latest()->first();

        if ($verification instanceof WalletKycVerification) {
            return $verification;
        }

        return WalletKycVerification::query()->create([
            'wallet_account_id' => $wallet->getKey(),
            'status' => 'not_started',
            'session_token' => Str::random(48),
            'session_expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Sandbox personal wallets use mock KYC: approve immediately without BUDERA_SANDBOX_FORCE_KYC_APPROVE.
     * Live wallets stay pending until a real provider / webhook completes verification.
     */
    private function shouldMockCompleteSandboxKyc(WalletAccount $wallet): bool
    {
        if (config('budera.sandbox.allow_force_kyc_approve')) {
            return true;
        }

        return $wallet->environment === 'sandbox';
    }

    private function completeSandboxKycIfEligible(WalletAccount $wallet, WalletKycVerification $verification): void
    {
        if (! $this->shouldMockCompleteSandboxKyc($wallet)) {
            return;
        }

        DB::transaction(function () use ($wallet, $verification): void {
            $verification->refresh();
            $wallet->refresh();

            if ($verification->status instanceof WalletKycVerificationApproved) {
                if (! $wallet->status instanceof WalletAccountActive) {
                    $wallet->status->transitionTo(WalletAccountActive::class);
                    $wallet->save();
                }

                return;
            }

            if (! $verification->status instanceof WalletKycVerificationPending) {
                return;
            }

            $verification->status->transitionTo(WalletKycVerificationApproved::class);
            $verification->verified_at = now();
            $verification->save();

            if (! $wallet->status instanceof WalletAccountActive) {
                $wallet->status->transitionTo(WalletAccountActive::class);
                $wallet->save();
            }
        });
    }
}
