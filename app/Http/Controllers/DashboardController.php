<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\WalletAccount;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $company = $user->firstCompany();

        if ($user->is_budera_admin && $company === null) {
            return Inertia::render('dashboard', [
                'walletCount' => 0,
                'activeKeyCount' => 0,
                'activeKeyPreview' => null,
                'recentWebhookDeliveries' => Inertia::defer(fn () => []),
                'kybStatus' => 'not_started',
                'liveEnabledAt' => null,
                'companyName' => 'Budera Admin',
            ]);
        }

        if ($company === null) {
            abort(403);
        }

        $activeKeys = ApiKey::query()
            ->forEnvironment()
            ->where('status', 'active')
            ->get();

        $firstKey = $activeKeys->first();
        $keyLast4 = $firstKey?->metadata['key_last4'] ?? null;
        $activeKeyPreview = $keyLast4 !== null
            ? "sk_sandbox_...{$keyLast4}"
            : null;

        return Inertia::render('dashboard', [
            'walletCount' => WalletAccount::query()->forEnvironment()->count(),
            'activeKeyCount' => $activeKeys->count(),
            'activeKeyPreview' => $activeKeyPreview,
            'recentWebhookDeliveries' => Inertia::defer(fn () => $this->recentDeliveries($company->id)),
            'kybStatus' => (string) $company->kyb_status,
            'liveEnabledAt' => $company->live_enabled_at?->toIso8601String(),
            'companyName' => $company->name,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentDeliveries(int $companyId): array
    {
        $endpointIds = WebhookEndpoint::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->pluck('id');

        return WebhookDelivery::query()
            ->whereIn('webhook_endpoint_id', $endpointIds)
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (WebhookDelivery $d) => [
                'id' => $d->id,
                'event' => $d->event,
                'status' => $d->status,
                'attempts' => (int) $d->attempts,
                'response_status' => $d->response_status,
                'last_attempted_at' => $d->last_attempted_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
