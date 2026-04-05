<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class OnboardingController extends Controller
{
    public function show(Request $request): RedirectResponse|Response
    {
        if ($request->user()?->isEndUser()) {
            return redirect()->route(
                $request->user()->isKycVerified() ? 'user.wallet.index' : 'user.kyc.show'
            );
        }

        if ($request->user()?->canAccessDashboard()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('onboarding');
    }

    public function storeCompany(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $company = Company::query()->create([
            'name' => $validated['name'],
            'owner_id' => $user->getKey(),
        ]);

        setPermissionsTeamId($company->getKey());
        $role = Role::findByName('company_owner', 'web');
        $user->assignRole($role);
        setPermissionsTeamId(null);

        return redirect()->route('dashboard');
    }

    public function acceptInvitation(Request $request, string $token): RedirectResponse
    {
        $invitation = CompanyInvitation::query()->where('token', $token)->firstOrFail();

        $user = $request->user();

        if ($invitation->isAccepted()) {
            return redirect()->route('onboarding')->with('status', 'This invitation was already accepted.');
        }

        if ($invitation->isExpired()) {
            return redirect()->route('onboarding')->with('error', 'This invitation has expired.');
        }

        if (strcasecmp((string) $invitation->email, (string) $user->email) !== 0) {
            return redirect()->route('onboarding')->with('error', 'Sign in as '.$invitation->email.' to accept this invitation.');
        }

        setPermissionsTeamId($invitation->company_id);
        $role = Role::findByName('company_developer', 'web');
        $user->assignRole($role);
        setPermissionsTeamId(null);

        $invitation->update(['accepted_at' => now()]);

        return redirect()->route('dashboard');
    }
}
