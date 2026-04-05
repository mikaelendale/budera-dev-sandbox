<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyDashboardEnvironmentController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'environment' => ['required', Rule::in(['sandbox', 'live'])],
        ]);

        $user = $request->user();
        $company = $user?->firstCompany();

        if ($company === null) {
            abort(403);
        }

        if ($validated['environment'] === 'live' && $company->live_enabled_at === null) {
            return back()->with('error', __('Live environment is not enabled for this company yet.'));
        }

        $minutes = 60 * 24 * 365;
        $cookieName = (string) config('budera.dashboard_environment_cookie');

        return back()->withCookie(cookie(
            $cookieName,
            $validated['environment'],
            $minutes,
            '/',
            null,
            (bool) config('session.secure'),
            true,
            false,
            config('session.same_site'),
        ));
    }
}
