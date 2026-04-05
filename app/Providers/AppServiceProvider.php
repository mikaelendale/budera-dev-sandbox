<?php

namespace App\Providers;

use App\Auth\ApiKeyGuard;
use App\Contracts\Banking\ColumnBankService;
use App\Contracts\BankLink\BankLinkService as BankLinkServiceContract;
use App\Contracts\Kyc\KycProvider;
use App\Http\Responses\ApiErrorResponse;
use App\Http\Responses\Fortify\LoginResponse;
use App\Http\Responses\Fortify\RegisterResponse;
use App\Http\Responses\Fortify\TwoFactorLoginResponse;
use App\Models\ApiKey;
use App\Models\Company;
use App\Models\OAuthClient;
use App\Models\WalletAccount;
use App\Models\WalletOauthGrant;
use App\Observers\CompanyObserver;
use App\Routing\WalletAccountRouteBinding;
use App\Services\Audit\AuditService;
use App\Services\Audit\AuthorizationLedgerService;
use App\Services\Audit\CorrelationId;
use App\Services\Banking\ColumnBankClient;
use App\Services\Banking\ColumnBankMock;
use App\Services\Banking\MockBankClient;
use App\Services\Banking\PartnerBankIntegrationResolver;
use App\Services\Banking\WalletProvisioningService;
use App\Services\BankLink\MockBankLinkService;
use App\Services\KybService;
use App\Services\Kyc\MockKycProvider;
use App\Services\PaymentService;
use App\Services\TopupService;
use App\Services\TransferService;
use App\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Scope;
use Laravel\Passport\Token;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, TwoFactorLoginResponse::class);

        $this->app->singleton(PartnerBankIntegrationResolver::class);

        $this->app->singleton(MockBankClient::class, fn ($app): MockBankClient => MockBankClient::fromResolver(
            $app->make(PartnerBankIntegrationResolver::class),
        ));

        $this->app->singleton(ColumnBankMock::class, fn ($app): ColumnBankMock => new ColumnBankMock(
            $app->make(MockBankClient::class),
        ));

        $this->app->singleton(ColumnBankClient::class);

        $this->app->singleton(ColumnBankService::class, function ($app): ColumnBankService {
            if ($app->make(PartnerBankIntegrationResolver::class)->useLiveColumnClient()) {
                return $app->make(ColumnBankClient::class);
            }

            return $app->make(ColumnBankMock::class);
        });

        $this->app->singleton(KycProvider::class, fn ($app): KycProvider => new MockKycProvider(
            $app->make(MockBankClient::class),
        ));

        $this->app->singleton(WalletProvisioningService::class);
        $this->app->singleton(KybService::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(TopupService::class);
        $this->app->singleton(TransferService::class);
        $this->app->singleton(AuthorizationLedgerService::class);

        $this->app->singleton(BankLinkServiceContract::class, MockBankLinkService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuth();
        $this->configureDefaults();
        $this->configureRateLimiting();

        Company::observe(CompanyObserver::class);

        WalletAccountRouteBinding::register();

        $this->configurePassport();
    }

    protected function configureAuth(): void
    {
        Auth::extend('api-key', function ($app, string $name, array $config): ApiKeyGuard {
            return new ApiKeyGuard($app['request']);
        });
    }

    protected function configurePassport(): void
    {
        Passport::useClientModel(OAuthClient::class);

        Passport::tokensCan(config('budera.oauth.token_scopes', []));

        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));

        Passport::authorizationView(function (array $parameters) {
            /** @var Client $client */
            $client = $parameters['client'];
            /** @var array<int, Scope> $scopes */
            $scopes = $parameters['scopes'];

            $oauthClient = $client instanceof OAuthClient
                ? $client
                : OAuthClient::query()->find($client->getKey());

            $tokenScopes = config('budera.oauth.token_scopes', []);
            $sensitiveIds = config('budera.oauth.sensitive_scope_ids', []);

            $requestedIds = collect($scopes)->map(fn (Scope $s): string => $s->id)->values()->all();

            $allowingSummaries = collect($requestedIds)
                ->map(fn (string $id): ?array => isset($tokenScopes[$id])
                    ? ['id' => $id, 'label' => (string) $tokenScopes[$id]]
                    : null)
                ->filter()
                ->values()
                ->all();

            $denyingSummaries = collect($sensitiveIds)
                ->filter(fn (string $id): bool => ! in_array($id, $requestedIds, true))
                ->map(fn (string $id): array => [
                    'id' => $id,
                    'label' => isset($tokenScopes[$id]) ? (string) $tokenScopes[$id] : $id,
                ])
                ->values()
                ->all();

            $companyPayload = null;
            $company = $oauthClient?->company;
            if ($company !== null) {
                $companyPayload = [
                    'name' => $company->name,
                    'logo_url' => $company->logo_url,
                ];
            }

            $agentName = null;
            $rawAgent = request()->query('agent_name');
            if (is_string($rawAgent)) {
                $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $rawAgent);
                $clean = trim((string) $clean);
                if ($clean !== '') {
                    $agentName = mb_substr($clean, 0, 80);
                }
            }

            $authUser = $parameters['user'] ?? request()->user();
            $walletPreview = null;
            $policyPreview = null;
            $walletParam = request()->query('wallet_account');
            if (is_string($walletParam) && $walletParam !== '' && $authUser !== null && $oauthClient?->company_id !== null) {
                $wallet = WalletAccount::query()->where('public_id', $walletParam)->first();
                if ($wallet instanceof WalletAccount
                    && (int) $wallet->company_id === (int) $oauthClient->company_id
                    && Gate::forUser($authUser)->allows('viewAsCompanyMember', $wallet)) {
                    $walletPreview = [
                        'public_id' => $wallet->public_id,
                        'environment' => (string) $wallet->environment,
                    ];
                    $policy = $wallet->policy;
                    if ($policy !== null) {
                        $blocked = is_array($policy->blocked_payees) ? $policy->blocked_payees : [];
                        $policyPreview = [
                            'per_tx_limit_usd' => $policy->per_tx_limit_usd !== null ? (string) $policy->per_tx_limit_usd : null,
                            'daily_spend_limit_usd' => $policy->daily_spend_limit_usd !== null ? (string) $policy->daily_spend_limit_usd : null,
                            'daily_tx_count' => $policy->daily_tx_count,
                            'allowed_categories' => is_array($policy->allowed_categories) ? $policy->allowed_categories : [],
                            'require_approval_above' => $policy->require_approval_above !== null ? (string) $policy->require_approval_above : null,
                            'blocked_payees_count' => count($blocked),
                            'business_hours_only' => (bool) $policy->business_hours_only,
                        ];
                    }
                }
            }

            return Inertia::render('oauth/authorize', [
                'client' => [
                    'id' => $client->getKey(),
                    'name' => $client->name,
                ],
                'company' => $companyPayload,
                'agentName' => $agentName,
                'walletPreview' => $walletPreview,
                'policyPreview' => $policyPreview,
                'allowingSummaries' => $allowingSummaries,
                'denyingSummaries' => $denyingSummaries,
                'scopes' => collect($scopes)->map(fn ($s) => [
                    'id' => $s->id,
                    'description' => $s->description,
                ])->values()->all(),
                'authToken' => $parameters['authToken'],
                'csrfToken' => csrf_token(),
                'approveAction' => route('passport.authorizations.approve'),
                'denyAction' => route('passport.authorizations.deny'),
            ]);
        });

        Token::created(function (Token $token): void {
            if ($token->user_id === null) {
                return;
            }

            $client = $token->client;

            WalletOauthGrant::query()->create([
                'oauth_access_token_id' => $token->id,
                'user_id' => $token->user_id,
                'oauth_client_id' => $token->client_id,
                'company_id' => $client?->company_id,
                'wallet_account_id' => null,
                'scopes' => $token->scopes,
            ]);

            app(AuditService::class)->recordDomainAudit([
                'stream' => 'developer',
                'actor_type' => 'user',
                'actor_id' => (string) $token->user_id,
                'action' => 'oauth.access_token.issued',
                'resource_type' => 'oauth_access_tokens',
                'resource_id' => (string) $token->id,
                'environment' => null,
                'metadata' => [
                    'oauth_client_id' => (string) $token->client_id,
                    'company_id' => $client?->company_id !== null ? (string) $client->company_id : null,
                    'scopes' => $token->scopes,
                ],
                'correlation_id' => CorrelationId::fromRequestOrGenerate(),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        });

        Token::updated(function (Token $token): void {
            if ($token->revoked && $token->wasChanged('revoked')) {
                WalletOauthGrant::query()
                    ->where('oauth_access_token_id', $token->id)
                    ->update(['revoked_at' => now()]);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api-company', function (Request $request) {
            /** @var array<string, int> $limits */
            $limits = config('budera.api_rate_limits', []);
            $defaultRpm = (int) ($limits['default'] ?? 120);

            /** @var ApiKey|null $apiKey */
            $apiKey = $request->attributes->get('api_key');
            if (! $apiKey instanceof ApiKey) {
                $apiKey = Auth::guard('api-key')->currentApiKey();
            }

            $companyId = null;
            $rpm = $defaultRpm;
            $key = 'ip_api:'.$request->ip();

            if ($apiKey instanceof ApiKey) {
                $companyId = (int) $apiKey->company_id;
                $tier = Company::query()->whereKey($companyId)->value('api_rate_limit_tier');
                $tierKey = is_string($tier) && $tier !== '' ? $tier : 'default';
                $rpm = (int) ($limits[$tierKey] ?? $defaultRpm);
                $key = 'api_key:'.$apiKey->getKey();
            } else {
                $companyId = app(CompanyContext::class)->companyId();
                if ($companyId !== null) {
                    $tier = Company::query()->whereKey($companyId)->value('api_rate_limit_tier');
                    $tierKey = is_string($tier) && $tier !== '' ? $tier : 'default';
                    $rpm = (int) ($limits[$tierKey] ?? $defaultRpm);
                    $key = 'company_api:'.$companyId;
                }
            }

            $rpm = max(1, $rpm);

            return Limit::perMinute($rpm)->by($key)->response(function (Request $_request, array $headers) {
                $response = ApiErrorResponse::json('rate_limit_exceeded', 429);
                foreach ($headers as $name => $values) {
                    foreach ((array) $values as $value) {
                        $response->headers->set($name, $value, false);
                    }
                }

                return $response;
            });
        });
    }
}
