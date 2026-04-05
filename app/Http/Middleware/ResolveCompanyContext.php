<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\User;
use App\Services\Audit\CorrelationId;
use App\Tenancy\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        CorrelationId::bootstrap($request);

        $user = $request->user();

        if (! $user instanceof User && $this->bearerLooksLikePassportJwt($request)) {
            $candidate = $request->user('api');

            if ($candidate instanceof User) {
                $user = $candidate;
            }
        }

        $bypass = false;
        $companyId = null;
        $environment = null;

        if ($user instanceof User) {
            if ($user->is_budera_admin) {
                $bypass = true;
            } else {
                $companyId = $user->firstCompany()?->getKey();
            }
        }

        /** @var ApiKey|null $requestApiKey */
        $requestApiKey = $request->attributes->get('api_key');
        if ($requestApiKey instanceof ApiKey && $environment === null) {
            $environment = $requestApiKey->environment;
        }

        if ($companyId === null) {
            $routeCompany = $request->route('company');

            if ($routeCompany instanceof Company) {
                $companyId = $routeCompany->getKey();
            } elseif (is_numeric($routeCompany)) {
                $companyId = (int) $routeCompany;
            }
        }

        if ($companyId === null) {
            $bearerToken = $request->bearerToken();

            if (is_string($bearerToken) && $bearerToken !== '') {
                $apiKey = ApiKey::query()
                    ->where('key_hash', hash('sha256', $bearerToken))
                    ->whereNull('revoked_at')
                    ->where('status', 'active')
                    ->first();

                if ($apiKey !== null) {
                    $companyId = (int) $apiKey->company_id;
                    $environment = $apiKey->environment;
                }
            }
        }

        if ($this->shouldApplyWebDashboardEnvironment($request, $user, $companyId, $bypass)) {
            $environment = $this->resolveWebDashboardEnvironment($request, (int) $companyId);
        }

        app()->instance(CompanyContext::class, new CompanyContext(
            companyId: $companyId,
            environment: $environment,
            bypassCompanyScope: $bypass,
        ));

        return $next($request);
    }

    private function bearerLooksLikePassportJwt(Request $request): bool
    {
        $bearer = $request->bearerToken();

        return is_string($bearer) && substr_count($bearer, '.') === 2;
    }

    private function shouldApplyWebDashboardEnvironment(Request $request, mixed $user, ?int $companyId, bool $bypass): bool
    {
        if ($bypass || $companyId === null || ! $user instanceof User) {
            return false;
        }

        if ($request->is('api/*')) {
            return false;
        }

        /** @var ApiKey|null $requestApiKey */
        $requestApiKey = $request->attributes->get('api_key');

        return ! ($requestApiKey instanceof ApiKey);
    }

    private function resolveWebDashboardEnvironment(Request $request, int $companyId): string
    {
        $company = Company::query()->find($companyId);

        $cookieName = (string) config('budera.dashboard_environment_cookie');
        $value = $request->cookie($cookieName, 'sandbox');

        if (! in_array($value, ['sandbox', 'live'], true)) {
            $value = 'sandbox';
        }

        if ($company === null || $company->live_enabled_at === null) {
            return 'sandbox';
        }

        return $value;
    }
}
