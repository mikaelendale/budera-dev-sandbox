<?php

namespace App\Http\Controllers;

use App\Contracts\BankLink\BankLinkService;
use App\Http\Requests\BankLinkSessionCredentialRequest;
use App\Http\Requests\BankLinkSessionVerifyRequest;
use App\Models\BankLink;
use App\Models\User;
use App\States\BankLink\BankLinkFailed;
use App\States\BankLink\BankLinkInitiated;
use App\States\BankLink\BankLinkMicrodepositSent;
use App\States\BankLink\BankLinkRevoked;
use App\States\BankLink\BankLinkVerified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class BankLinkSessionController extends Controller
{
    public function __construct(
        private readonly BankLinkService $bankLinkService,
    ) {}

    public function show(Request $request): Response
    {
        $link = $this->resolveLink($request);
        $expired = (bool) $request->attributes->get('bank_link_session_expired');
        $token = (string) $request->attributes->get('bank_link_session_token');

        $company = $link->company;
        $companyName = $company?->name ?? config('app.name');

        return Inertia::render('bank-link/session', $this->pageProps($link, $expired, $token, $companyName));
    }

    public function storeCredentials(BankLinkSessionCredentialRequest $request): RedirectResponse
    {
        $link = $this->resolveLink($request);
        $token = (string) $request->attributes->get('bank_link_session_token');

        if ((bool) $request->attributes->get('bank_link_session_expired')) {
            return redirect()->route('bank-link.show', ['sessionToken' => $token])
                ->with('error', __('This bank link session has expired.'));
        }

        if (! $link->status instanceof BankLinkInitiated) {
            return redirect()->route('bank-link.show', ['sessionToken' => $token])
                ->with('error', __('Bank details were already submitted for this session.'));
        }

        try {
            $this->bankLinkService->submitCredentials($link, [
                'routing_number' => (string) $request->validated('routing_number'),
                'account_number' => (string) $request->validated('account_number'),
                'bank_slug' => $request->validated('bank_slug'),
            ]);
        } catch (InvalidArgumentException) {
            return redirect()->route('bank-link.show', ['sessionToken' => $token])
                ->with('error', __('Please check your routing and account numbers.'));
        }

        return redirect()->route('bank-link.show', ['sessionToken' => $token])
            ->with('status', __('Micro-deposits are on the way.'));
    }

    public function verify(BankLinkSessionVerifyRequest $request): RedirectResponse
    {
        $link = $this->resolveLink($request);
        $token = (string) $request->attributes->get('bank_link_session_token');

        if ((bool) $request->attributes->get('bank_link_session_expired')) {
            return redirect()->route('bank-link.show', ['sessionToken' => $token])
                ->with('error', __('This bank link session has expired.'));
        }

        if (! $link->status instanceof BankLinkMicrodepositSent) {
            return redirect()->route('bank-link.show', ['sessionToken' => $token])
                ->with('error', __('This session is not awaiting verification.'));
        }

        $actor = User::query()->find($link->user_id);
        if (! $actor instanceof User) {
            abort(500);
        }

        try {
            $this->bankLinkService->verifyMicrodeposits(
                $link,
                $actor,
                (int) $request->validated('amount_first_cents'),
                (int) $request->validated('amount_second_cents'),
            );
        } catch (InvalidArgumentException) {
            $link->refresh();

            return redirect()->route('bank-link.show', ['sessionToken' => $token])
                ->with('error', __('Those amounts do not match. Please try again.'));
        }

        return redirect()->route('bank-link.show', ['sessionToken' => $token]);
    }

    private function resolveLink(Request $request): BankLink
    {
        $link = $request->attributes->get('bank_link');
        if (! $link instanceof BankLink) {
            abort(404);
        }

        return $link;
    }

    /**
     * @return array<string, mixed>
     */
    private function pageProps(BankLink $link, bool $expired, string $token, string $companyName): array
    {
        $status = $link->status->getValue();
        $meta = is_array($link->metadata) ? $link->metadata : [];
        $sandboxDoc = is_string($meta['sandbox_microdeposit_documentation'] ?? null)
            ? (string) $meta['sandbox_microdeposit_documentation']
            : null;

        $attemptsRemaining = null;
        if ($link->status instanceof BankLinkMicrodepositSent) {
            $attemptsRemaining = max(0, 3 - (int) $link->failed_verification_attempts);
        }

        $step = match (true) {
            $link->status instanceof BankLinkVerified => 'success',
            $link->status instanceof BankLinkFailed => 'locked',
            $link->status instanceof BankLinkRevoked => 'revoked',
            $expired && ! $link->status instanceof BankLinkVerified => 'expired',
            $link->status instanceof BankLinkInitiated => 'credentials',
            $link->status instanceof BankLinkMicrodepositSent => 'verify',
            default => 'unknown',
        };

        return [
            'token' => $token,
            'companyName' => $companyName,
            'environment' => $link->environment,
            'bankLinkStatus' => $status,
            'step' => $step,
            'expired' => $expired && ! $link->status instanceof BankLinkVerified,
            'sandboxMicrodepositDocumentation' => $link->environment === 'sandbox' ? $sandboxDoc : null,
            'attemptsRemaining' => $attemptsRemaining,
            'accountLast4' => $link->account_last4,
            'credentialsAction' => route('bank-link.credentials', ['sessionToken' => $token]),
            'verifyAction' => route('bank-link.verify', ['sessionToken' => $token]),
        ];
    }
}
