<?php

namespace App\Http\Responses\Fortify;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    use RedirectAfterAuth;

    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
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
