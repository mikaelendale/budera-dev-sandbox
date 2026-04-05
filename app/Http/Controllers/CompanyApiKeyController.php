<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Services\Audit\AuditService;
use App\Services\Audit\CorrelationId;
use App\Services\Audit\TransitionRecorder;
use App\States\ApiKey\ApiKeyRevoked;
use App\States\ApiKey\ApiKeyRotated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompanyApiKeyController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly TransitionRecorder $transitionRecorder,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $company = $request->user()?->firstCompany();

        if ($company === null) {
            return redirect()->route('dashboard');
        }

        if (! $request->user()->hasCompanyPermission($company, 'company.keys.view')) {
            abort(403);
        }

        $apiKeys = ApiKey::query()
            ->forEnvironment()
            ->where('company_id', $company->id)
            ->latest()
            ->get()
            ->map(function (ApiKey $apiKey): array {
                $last4 = is_array($apiKey->metadata)
                    ? ($apiKey->metadata['key_last4'] ?? '****')
                    : '****';

                return [
                    'id' => $apiKey->getKey(),
                    'environment' => $apiKey->environment,
                    'status' => (string) $apiKey->status,
                    'abilities' => $apiKey->abilities ?? [],
                    'key_preview' => '••••'.(string) $last4,
                    'created_at' => $apiKey->created_at?->toIso8601String(),
                    'revoked_at' => $apiKey->revoked_at?->toIso8601String(),
                ];
            })
            ->values();

        return Inertia::render('company/api-keys', [
            'apiKeys' => $apiKeys,
            'oneTimePlainTextKey' => session('one_time_plain_text_key'),
            'defaultEnvironment' => 'sandbox',
            'defaultAbilities' => ['wallet:read', 'wallet:pay', 'sandbox:simulate'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $request->user()?->firstCompany();

        if ($company === null) {
            abort(403);
        }

        if (! $request->user()->hasCompanyPermission($company, 'company.keys.manage')) {
            abort(403);
        }

        $validated = $request->validate([
            'environment' => ['required', 'in:sandbox,live'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string'],
        ]);

        if ($validated['environment'] === 'live' && $company->live_enabled_at === null) {
            return redirect()->back()->withErrors([
                'environment' => __('Live API keys require Budera admin approval first.'),
            ]);
        }

        $plainTextKey = $this->generatePlainTextKey($validated['environment']);

        $apiKey = ApiKey::query()->create([
            'company_id' => $company->id,
            'environment' => $validated['environment'],
            'status' => 'active',
            'key_hash' => hash('sha256', $plainTextKey),
            'abilities' => array_values(array_unique($validated['abilities'])),
            'metadata' => [
                'key_last4' => substr($plainTextKey, -4),
            ],
        ]);

        $this->auditService->recordDomainAudit([
            'stream' => 'developer',
            'actor_type' => 'user',
            'actor_id' => (string) $request->user()->getKey(),
            'action' => 'api_key.created',
            'resource_type' => 'api_keys',
            'resource_id' => (string) $apiKey->getKey(),
            'environment' => $validated['environment'],
            'metadata' => [
                'company_id' => (string) $company->getKey(),
                'abilities' => $apiKey->abilities ?? [],
            ],
            'correlation_id' => CorrelationId::current($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('company.api-keys.index')
            ->with('status', __('API key created. Copy it now; it will not be shown again.'))
            ->with('one_time_plain_text_key', $plainTextKey);
    }

    public function revoke(Request $request, ApiKey $apiKey): RedirectResponse
    {
        $company = $request->user()?->firstCompany();

        if ($company === null || (int) $apiKey->company_id !== (int) $company->id) {
            abort(403);
        }

        if (! $request->user()->hasCompanyPermission($company, 'company.keys.manage')) {
            abort(403);
        }

        if ((string) $apiKey->status !== 'revoked') {
            $from = $apiKey->status->getValue();
            $apiKey = $apiKey->status->transitionTo(ApiKeyRevoked::class);
            $apiKey->forceFill([
                'revoked_at' => now(),
            ])->save();

            $fresh = $apiKey->fresh();
            if ($fresh !== null) {
                $this->transitionRecorder->record(
                    $fresh,
                    $from,
                    'revoked',
                    [
                        'stream' => 'developer',
                        'actor_type' => 'user',
                        'actor_id' => (string) $request->user()->getKey(),
                        'action' => 'api_key.revoked',
                        'resource_type' => 'api_keys',
                        'resource_id' => (string) $fresh->getKey(),
                        'environment' => $fresh->environment,
                        'metadata' => [
                            'company_id' => (string) $company->getKey(),
                        ],
                        'correlation_id' => CorrelationId::current($request),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ],
                );
            }
        }

        return redirect()->back()->with('status', __('API key revoked.'));
    }

    public function rotate(Request $request, ApiKey $apiKey): RedirectResponse
    {
        $company = $request->user()?->firstCompany();

        if ($company === null || (int) $apiKey->company_id !== (int) $company->id) {
            abort(403);
        }

        if (! $request->user()->hasCompanyPermission($company, 'company.keys.manage')) {
            abort(403);
        }

        if ((string) $apiKey->status !== 'active') {
            return redirect()->back()->withErrors([
                'rotate' => __('Only active API keys can be rotated.'),
            ]);
        }

        $from = $apiKey->status->getValue();
        $apiKey = $apiKey->status->transitionTo(ApiKeyRotated::class);
        $apiKey->forceFill([
            'revoked_at' => now(),
        ])->save();

        $rotated = $apiKey->fresh();
        if ($rotated !== null) {
            $this->transitionRecorder->record(
                $rotated,
                $from,
                'rotated',
                [
                    'stream' => 'developer',
                    'actor_type' => 'user',
                    'actor_id' => (string) $request->user()->getKey(),
                    'action' => 'api_key.rotated',
                    'resource_type' => 'api_keys',
                    'resource_id' => (string) $rotated->getKey(),
                    'environment' => $rotated->environment,
                    'metadata' => [
                        'company_id' => (string) $company->getKey(),
                    ],
                    'correlation_id' => CorrelationId::current($request),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            );
        }

        $apiKey->refresh();

        $plainTextKey = $this->generatePlainTextKey($apiKey->environment);

        ApiKey::query()->create([
            'company_id' => $company->id,
            'environment' => $apiKey->environment,
            'status' => 'active',
            'key_hash' => hash('sha256', $plainTextKey),
            'abilities' => $apiKey->abilities ?? [],
            'metadata' => [
                'key_last4' => substr($plainTextKey, -4),
                'rotated_from_api_key_id' => $apiKey->id,
            ],
        ]);

        $this->auditService->recordDomainAudit([
            'stream' => 'developer',
            'actor_type' => 'user',
            'actor_id' => (string) $request->user()->getKey(),
            'action' => 'api_key.rotation_issued',
            'resource_type' => 'api_keys',
            'resource_id' => (string) $apiKey->getKey(),
            'environment' => $apiKey->environment,
            'metadata' => [
                'company_id' => (string) $company->getKey(),
            ],
            'correlation_id' => CorrelationId::current($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('company.api-keys.index')
            ->with('status', __('API key rotated. Copy the new key now.'))
            ->with('one_time_plain_text_key', $plainTextKey);
    }

    private function generatePlainTextKey(string $environment): string
    {
        return 'sk_'.$environment.'_'.Str::random(42);
    }
}
