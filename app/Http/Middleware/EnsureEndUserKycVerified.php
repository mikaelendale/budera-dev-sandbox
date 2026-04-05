<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEndUserKycVerified
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isEndUser() && ! $user->isKycVerified()) {
            return redirect()->route('user.kyc.show');
        }

        return $next($request);
    }
}
