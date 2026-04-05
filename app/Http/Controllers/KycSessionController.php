<?php

namespace App\Http\Controllers;

use App\Models\WalletKycVerification;
use App\States\WalletKycVerification\WalletKycVerificationApproved;
use App\States\WalletKycVerification\WalletKycVerificationNotStarted;
use App\States\WalletKycVerification\WalletKycVerificationPending;
use App\States\WalletKycVerification\WalletKycVerificationRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KycSessionController extends Controller
{
    public function show(Request $request): Response
    {
        $verification = $this->resolveVerification($request);
        $verification->loadMissing('walletAccount');

        if ($verification->status instanceof WalletKycVerificationPending && $this->shouldAutoApproveHostedSandboxKyc($verification)) {
            $verification->status->transitionTo(WalletKycVerificationApproved::class);
            $verification->verified_at = now();
            $verification->save();
            $verification->refresh();
        }

        $expired = $verification->session_expires_at !== null && $verification->session_expires_at->isPast();

        $step = match (true) {
            $expired => 'expired',
            $verification->status instanceof WalletKycVerificationApproved => 'approved',
            $verification->status instanceof WalletKycVerificationRejected => 'rejected',
            $verification->status instanceof WalletKycVerificationPending => 'verifying',
            default => 'identity',
        };

        return Inertia::render('kyc/session', [
            'sessionToken' => (string) $request->attributes->get('kyc_session_token'),
            'step' => $step,
            'status' => $verification->status->getValue(),
            'walletPublicId' => $verification->walletAccount?->public_id,
            'submitAction' => route('kyc.submit', ['sessionToken' => $request->attributes->get('kyc_session_token')]),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $verification = $this->resolveVerification($request);
        $token = (string) $request->attributes->get('kyc_session_token');

        if ($verification->session_expires_at?->isPast()) {
            return redirect()->route('kyc.show', ['sessionToken' => $token])
                ->with('error', 'This verification session has expired.');
        }

        if (! $verification->status instanceof WalletKycVerificationNotStarted) {
            return redirect()->route('kyc.show', ['sessionToken' => $token])
                ->with('error', 'Verification has already been submitted.');
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

        $verification->status->transitionTo(WalletKycVerificationPending::class);
        $verification->save();

        $verification->loadMissing('walletAccount');

        if ($this->shouldAutoApproveHostedSandboxKyc($verification)) {
            $verification->status->transitionTo(WalletKycVerificationApproved::class);
            $verification->verified_at = now();
            $verification->save();
        }

        return redirect()->route('kyc.show', ['sessionToken' => $token]);
    }

    private function resolveVerification(Request $request): WalletKycVerification
    {
        $verification = $request->attributes->get('kyc_verification');
        if (! $verification instanceof WalletKycVerification) {
            abort(404);
        }

        return $verification;
    }

    private function shouldAutoApproveHostedSandboxKyc(WalletKycVerification $verification): bool
    {
        if (config('budera.sandbox.allow_force_kyc_approve')) {
            return true;
        }

        return $verification->walletAccount?->environment === 'sandbox';
    }
}
