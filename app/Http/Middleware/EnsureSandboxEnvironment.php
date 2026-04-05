<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiErrorResponse;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts routes to sandbox API keys only. OAuth/Personal tokens without an API key are rejected.
 */
class EnsureSandboxEnvironment
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.env') === 'production') {
            return ApiErrorResponse::json('sandbox_disabled_production');
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if (! $apiKey instanceof ApiKey) {
            $apiKey = Auth::guard('api-key')->currentApiKey();
        }

        if (! $apiKey instanceof ApiKey) {
            return ApiErrorResponse::json('simulation_requires_api_key');
        }

        if ($apiKey->environment !== 'sandbox') {
            return ApiErrorResponse::json('simulation_forbidden_live_environment');
        }

        return $next($request);
    }
}
