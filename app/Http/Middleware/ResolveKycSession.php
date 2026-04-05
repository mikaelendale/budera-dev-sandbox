<?php

namespace App\Http\Middleware;

use App\Models\WalletKycVerification;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveKycSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('sessionToken');

        if (! is_string($token) || $token === '') {
            abort(404);
        }

        $verification = WalletKycVerification::query()
            ->where('session_token', $token)
            ->first();

        if (! $verification instanceof WalletKycVerification) {
            abort(404);
        }

        $request->attributes->set('kyc_verification', $verification);
        $request->attributes->set('kyc_session_token', $token);

        return $next($request);
    }
}
