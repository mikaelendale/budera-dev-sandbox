<?php

namespace App\Http\Controllers;

use App\Services\Audit\AuditService;
use App\Services\Audit\CorrelationId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyLiveAccessController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user?->firstCompany();

        if ($company === null) {
            abort(403);
        }

        abort_unless((int) $company->owner_id === (int) $user->getKey(), 403);

        if ((string) $company->kyb_status !== 'approved') {
            return redirect()->route('company.settings')
                ->withErrors(['live_access' => __('KYB must be approved before requesting live access.')]);
        }

        if ($company->live_enabled_at !== null) {
            return redirect()->route('company.settings')
                ->withErrors(['live_access' => __('Live access is already enabled.')]);
        }

        $this->auditService->recordDomainAudit([
            'stream' => 'developer',
            'actor_type' => 'user',
            'actor_id' => (string) $user->getKey(),
            'action' => 'live_access.requested',
            'resource_type' => 'companies',
            'resource_id' => (string) $company->getKey(),
            'environment' => 'live',
            'metadata' => [
                'company_id' => (string) $company->getKey(),
            ],
            'correlation_id' => CorrelationId::current($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('company.settings')
            ->with('status', __('Live access request submitted. Budera will review and enable production access.'));
    }
}
