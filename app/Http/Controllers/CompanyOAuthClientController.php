<?php

namespace App\Http\Controllers;

use App\Models\OAuthClient;
use App\Services\Audit\AuditService;
use App\Services\Audit\CorrelationId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\ClientRepository;

class CompanyOAuthClientController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $company = $request->user()?->firstCompany();

        if ($company === null) {
            return redirect()->route('dashboard');
        }

        if (! $request->user()->canManageCompanyInvites($company)) {
            abort(403);
        }

        $clients = OAuthClient::query()
            ->where('company_id', $company->id)
            ->where('revoked', false)
            ->orderBy('name')
            ->get()
            ->map(fn (OAuthClient $c) => [
                'id' => $c->getKey(),
                'name' => $c->name,
                'redirect_uris' => $c->redirect_uris ?? [],
                'confidential' => $c->confidential(),
            ]);

        return Inertia::render('company/oauth-apps', [
            'clients' => $clients,
        ]);
    }

    public function store(Request $request, ClientRepository $clients): RedirectResponse
    {
        $company = $request->user()?->firstCompany();

        if ($company === null) {
            abort(403);
        }

        if (! $request->user()->canManageCompanyInvites($company)) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'redirect_uri' => ['required', 'string', 'url', 'max:2048'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $isPublic = (bool) ($validated['is_public'] ?? false);

        $client = $clients->createAuthorizationCodeGrantClient(
            $validated['name'],
            [rtrim($validated['redirect_uri'], '/')],
            ! $isPublic,
            $request->user(),
        );

        $plainSecret = $client->plainSecret;

        $client->forceFill(['company_id' => $company->id])->save();

        $this->auditService->recordDomainAudit([
            'stream' => 'developer',
            'actor_type' => 'user',
            'actor_id' => (string) $request->user()->getKey(),
            'action' => 'oauth_client.created',
            'resource_type' => 'oauth_clients',
            'resource_id' => (string) $client->getKey(),
            'environment' => null,
            'metadata' => [
                'company_id' => (string) $company->getKey(),
                'name' => (string) $client->name,
            ],
            'correlation_id' => CorrelationId::current($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $redirect = redirect()->back()->with(
            'status',
            $isPublic
                ? __('OAuth client created. Use PKCE (no client secret) for the token exchange.')
                : __('OAuth client created. Copy your client secret now — it will not be shown again.'),
        );

        if (! $isPublic && is_string($plainSecret) && $plainSecret !== '') {
            $redirect->with('oauth_client_credentials', [
                'client_id' => (string) $client->getKey(),
                'client_secret' => $plainSecret,
            ]);
        }

        return $redirect;
    }

    public function destroy(Request $request, OAuthClient $client, ClientRepository $repository): RedirectResponse
    {
        $company = $request->user()?->firstCompany();

        if ($company === null || (int) $client->company_id !== (int) $company->id) {
            abort(403);
        }

        if (! $request->user()->canManageCompanyInvites($company)) {
            abort(403);
        }

        $clientId = (string) $client->getKey();

        $repository->delete($client);

        $this->auditService->recordDomainAudit([
            'stream' => 'developer',
            'actor_type' => 'user',
            'actor_id' => (string) $request->user()->getKey(),
            'action' => 'oauth_client.revoked',
            'resource_type' => 'oauth_clients',
            'resource_id' => $clientId,
            'environment' => null,
            'metadata' => [
                'company_id' => (string) $company->getKey(),
            ],
            'correlation_id' => CorrelationId::current($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back()->with('status', __('OAuth client revoked.'));
    }
}
