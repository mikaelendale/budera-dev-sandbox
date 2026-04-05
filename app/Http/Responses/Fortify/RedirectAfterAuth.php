<?php

namespace App\Http\Responses\Fortify;

use Illuminate\Http\Request;

trait RedirectAfterAuth
{
    protected function redirectPath(Request $request): string
    {
        $user = $request->user();

        if ($user !== null && $user->isEndUser()) {
            if ($user->isKycVerified()) {
                return route('user.wallet.index', absolute: false);
            }

            return route('user.kyc.show', absolute: false);
        }

        if ($user !== null && $user->canAccessDashboard()) {
            return route('dashboard', absolute: false);
        }

        return route('onboarding', absolute: false);
    }
}
