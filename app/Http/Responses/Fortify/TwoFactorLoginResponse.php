<?php

namespace App\Http\Responses\Fortify;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    use RedirectAfterAuth;

    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $user = $request->user();

        if ($user !== null && $user->isEndUser()) {
            return redirect()->to($this->redirectPath($request));
        }

        if ($user !== null && $user->canAccessDashboard()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        return redirect()->to($this->redirectPath($request));
    }
}
