<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasCompanyAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isEndUser()) {
            return redirect()->route($user->isKycVerified() ? 'user.wallet.index' : 'user.kyc.show');
        }

        if ($user === null || ! $user->canAccessDashboard()) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
