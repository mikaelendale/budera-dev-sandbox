<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\LiveAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class LiveAccessController extends Controller
{
    public function index(): Response
    {
        $companies = Company::query()
            ->where('kyb_status', 'approved')
            ->whereNull('live_enabled_at')
            ->with('owner')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(fn (Company $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'owner_email' => $c->owner?->email,
                'kyb_status' => (string) $c->kyb_status,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/live-access/index', [
            'companies' => $companies,
        ]);
    }

    public function approve(Request $request, Company $company, LiveAccessService $liveAccessService): RedirectResponse
    {
        try {
            $liveAccessService->approveLiveAccess($company, $request->user(), $request);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.live-access.index')
                ->withErrors(['live_access' => $e->getMessage()]);
        }

        return redirect()->route('admin.live-access.index')
            ->with('status', __('Live access enabled for :name.', ['name' => $company->name]));
    }
}
