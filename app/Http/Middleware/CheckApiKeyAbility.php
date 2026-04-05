<?php

namespace App\Http\Middleware;

use App\Auth\ApiKeyGuard;
use App\Http\Responses\ApiErrorResponse;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckApiKeyAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey instanceof ApiKey) {
            foreach ($abilities as $ability) {
                if (! $apiKey->hasAbility($ability)) {
                    return ApiErrorResponse::json('missing_api_key_ability', 403, ['ability' => $ability]);
                }
            }

            return $next($request);
        }

        /** @var ApiKeyGuard $guard */
        $guard = Auth::guard('api-key');
        $guardApiKey = $guard->currentApiKey();

        if ($guardApiKey instanceof ApiKey) {
            foreach ($abilities as $ability) {
                if (! $guardApiKey->hasAbility($ability)) {
                    return ApiErrorResponse::json('missing_api_key_ability', 403, ['ability' => $ability]);
                }
            }

            return $next($request);
        }

        $user = $request->user('api');

        if ($user !== null && method_exists($user, 'tokenCan')) {
            foreach ($abilities as $ability) {
                if (! $user->tokenCan($ability)) {
                    return ApiErrorResponse::json('missing_token_scope', 403, ['scope' => $ability]);
                }
            }

            return $next($request);
        }

        return ApiErrorResponse::json('unauthenticated_api', 401);
    }
}
