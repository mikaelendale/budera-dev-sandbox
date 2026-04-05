<?php

use App\Http\Middleware\CheckApiKeyAbility;
use App\Http\Middleware\EnsureBankPartner;
use App\Http\Middleware\EnsureBuderaAdmin;
use App\Http\Middleware\EnsureEndUser;
use App\Http\Middleware\EnsureEndUserKycVerified;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsureSandboxEnvironment;
use App\Http\Middleware\EnsureSandboxKycForceApprove;
use App\Http\Middleware\EnsureUserHasCompanyAccess;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveBankLinkSession;
use App\Http\Middleware\ResolveCompanyContext;
use App\Http\Middleware\ResolveKycSession;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Middleware\CheckToken;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            ResolveCompanyContext::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(prepend: [
            ResolveCompanyContext::class,
        ]);

        $middleware->alias([
            'bank-link.session' => ResolveBankLinkSession::class,
            'kyc.session' => ResolveKycSession::class,
            'company.onboarded' => EnsureUserHasCompanyAccess::class,
            'budera.admin' => EnsureBuderaAdmin::class,
            'end.user' => EnsureEndUser::class,
            'end.user.kyc' => EnsureEndUserKycVerified::class,
            'scopes' => CheckToken::class,
            'api-key.abilities' => CheckApiKeyAbility::class,
            'idempotency' => EnsureIdempotency::class,
            'sandbox.kyc' => EnsureSandboxKycForceApprove::class,
            'sandbox.environment' => EnsureSandboxEnvironment::class,
            'bank.partner' => EnsureBankPartner::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::json('unauthenticated_api', 401);
            }

            return null;
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::json('forbidden', 403);
            }

            return null;
        });

        /*
         | Laravel converts ModelNotFoundException to NotFoundHttpException before custom render
         | callbacks run, so we must handle NotFoundHttpException and unwrap the previous
         | exception to return the canonical API error envelope for missing models.
         */
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                return ApiErrorResponse::json('resource_not_found', 404);
            }

            return null;
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                $response = ApiErrorResponse::json('rate_limit_exceeded', 429);
                $headers = $e->getHeaders();
                if (isset($headers['Retry-After'])) {
                    $response->headers->set('Retry-After', $headers['Retry-After']);
                }

                return $response;
            }

            return null;
        });
    })->create();
