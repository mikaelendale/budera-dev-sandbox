<?php

namespace App\Http\Middleware;

use App\Models\BankLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBankLinkSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('sessionToken');

        if (! is_string($token) || strlen($token) < 32) {
            abort(404);
        }

        $hash = hash('sha256', $token);

        $link = BankLink::query()->where('session_token_hash', $hash)->first();

        if (! $link instanceof BankLink) {
            abort(404);
        }

        $expired = $link->session_expires_at !== null && $link->session_expires_at->isPast();

        $request->attributes->set('bank_link', $link);
        $request->attributes->set('bank_link_session_token', $token);
        $request->attributes->set('bank_link_session_expired', $expired);

        return $next($request);
    }
}
