<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Audit\CorrelationId;
use App\Tenancy\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $company = $user?->firstCompany();

        $context = app()->bound(CompanyContext::class) ? app(CompanyContext::class) : null;
        $dashboardEnvironment = null;
        $companyLiveEnabled = false;
        $canSwitchDashboardEnvironment = false;

        if ($company !== null) {
            $companyLiveEnabled = $company->live_enabled_at !== null;
            $canSwitchDashboardEnvironment = $companyLiveEnabled;
            $env = $context?->environment();
            $dashboardEnvironment = $env ?? ($user?->is_budera_admin ? null : 'sandbox');
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'correlationId' => CorrelationId::current($request),
            'isBuderaAdmin' => (bool) ($user?->is_budera_admin ?? false),
            'isBankPartner' => (bool) $this->isBankPartner($user),
            'isEndUser' => (bool) ($user?->isEndUser() ?? false),
            'isKycVerified' => (bool) ($user?->isEndUser() && $user->isKycVerified()),
            'auth' => [
                'user' => $user,
            ],
            'company' => $company === null ? null : [
                'id' => $company->id,
                'name' => $company->name,
            ],
            'dashboardEnvironment' => $dashboardEnvironment,
            'companyLiveEnabled' => $companyLiveEnabled,
            'canSwitchDashboardEnvironment' => $canSwitchDashboardEnvironment,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'status' => $request->session()->get('status'),
                'error' => $request->session()->get('error'),
                'oauth_client_credentials' => $request->session()->get('oauth_client_credentials'),
            ],
        ];
    }

    private function isBankPartner(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $pivotTable = config('permission.table_names.model_has_roles');

        return DB::table($pivotTable)
            ->join('roles', 'roles.id', '=', $pivotTable.'.role_id')
            ->where($pivotTable.'.model_id', $user->getKey())
            ->where($pivotTable.'.model_type', $user->getMorphClass())
            ->where('roles.name', 'bank_partner')
            ->exists();
    }
}
