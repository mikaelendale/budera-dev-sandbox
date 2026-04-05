<?php

namespace App\Http\Controllers;

use App\Models\CompanyInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyTeamController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $company = $user?->firstCompany();

        if ($company === null) {
            return redirect()->route('dashboard');
        }

        $canManageInvites = $user->canManageCompanyInvites($company);

        $members = $company->membersWithRoles()->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'email' => (string) $row->email,
            'role' => (string) $row->role,
        ])->values()->all();

        $pendingInvitations = CompanyInvitation::query()
            ->where('company_id', $company->id)
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CompanyInvitation $invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'created_at' => $invitation->created_at?->toIso8601String(),
                'is_expired' => $invitation->isExpired(),
            ])
            ->values()
            ->all();

        return Inertia::render('company/team', [
            'canManageInvites' => $canManageInvites,
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
        ]);
    }
}
