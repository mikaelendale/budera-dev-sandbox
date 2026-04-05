<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Notifications\Transactional\LiveAccessApprovedNotification;
use App\Services\Audit\AuditService;
use App\Services\Audit\CorrelationId;
use App\Services\Mail\TransactionalMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LiveAccessService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function approveLiveAccess(Company $company, User $admin, ?Request $request = null): Company
    {
        if ((string) $company->kyb_status !== 'approved') {
            throw new InvalidArgumentException('company_kyb_not_approved');
        }

        if ($company->live_enabled_at !== null) {
            throw new InvalidArgumentException('company_already_live_enabled');
        }

        return DB::transaction(function () use ($company, $admin, $request): Company {
            $company->live_enabled_at = now();
            $company->save();

            $this->auditService->recordDomainAudit([
                'stream' => 'internal_admin',
                'actor_type' => 'user',
                'actor_id' => (string) $admin->getKey(),
                'action' => 'company.live_enabled',
                'resource_type' => 'companies',
                'resource_id' => (string) $company->getKey(),
                'environment' => 'live',
                'metadata' => [
                    'company_id' => (string) $company->getKey(),
                    'admin_user_id' => (string) $admin->getKey(),
                ],
                'correlation_id' => $request !== null ? CorrelationId::current($request) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);

            $this->auditService->enqueueWebhook('live.enabled', [
                'event' => 'live.enabled',
                'data' => [
                    'company_id' => (string) $company->getKey(),
                ],
            ], [
                'company_id' => (int) $company->getKey(),
                'environment' => 'live',
                'event_id' => (string) Str::uuid(),
            ]);

            $owner = $company->owner;
            TransactionalMail::notifyUser($owner, new LiveAccessApprovedNotification($company));

            return $company->fresh();
        });
    }
}
