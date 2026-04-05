<?php

namespace App\Http\Controllers;

use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\Transactional\CompanyInvitationNotification;
use App\Services\Audit\AuditService;
use App\Services\Audit\CorrelationId;
use App\Services\Mail\TransactionalMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyInvitationController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $company = $request->user()?->firstCompany();

        if ($company === null) {
            abort(403);
        }

        if (! $request->user()->canManageCompanyInvites($company)) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));

        $existingUser = User::query()->where('email', $email)->first();
        if ($existingUser !== null && $existingUser->isMemberOfCompany($company)) {
            throw ValidationException::withMessages([
                'email' => __('This person is already a member of this organization.'),
            ]);
        }

        $pendingDuplicate = CompanyInvitation::query()
            ->where('company_id', $company->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->exists();

        if ($pendingDuplicate) {
            throw ValidationException::withMessages([
                'email' => __('An invitation is already pending for this email.'),
            ]);
        }

        $invitation = CompanyInvitation::query()->create([
            'company_id' => $company->id,
            'email' => $email,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $acceptUrl = route('invitations.accept', ['token' => $invitation->token]);

        TransactionalMail::notifyEmail($email, new CompanyInvitationNotification(
            companyName: $company->name,
            acceptUrl: $acceptUrl,
            inviteeEmail: $email,
        ));

        $this->auditService->recordDomainAudit([
            'stream' => 'developer',
            'actor_type' => 'user',
            'actor_id' => (string) $request->user()->getKey(),
            'action' => 'company_invitation.sent',
            'resource_type' => 'company_invitations',
            'resource_id' => (string) $invitation->getKey(),
            'environment' => null,
            'metadata' => [
                'company_id' => (string) $company->getKey(),
                'email' => $email,
            ],
            'correlation_id' => CorrelationId::current($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back()->with('status', __('Invitation sent.'));
    }

    public function destroy(Request $request, CompanyInvitation $invitation): RedirectResponse
    {
        $company = $request->user()?->firstCompany();

        if ($company === null || $invitation->company_id !== $company->id) {
            abort(403);
        }

        if (! $request->user()->canManageCompanyInvites($company)) {
            abort(403);
        }

        if ($invitation->isAccepted()) {
            abort(403);
        }

        $invitationId = (string) $invitation->getKey();
        $email = (string) $invitation->email;

        $invitation->delete();

        $this->auditService->recordDomainAudit([
            'stream' => 'developer',
            'actor_type' => 'user',
            'actor_id' => (string) $request->user()->getKey(),
            'action' => 'company_invitation.revoked',
            'resource_type' => 'company_invitations',
            'resource_id' => $invitationId,
            'environment' => null,
            'metadata' => [
                'company_id' => (string) $company->getKey(),
                'email' => $email,
            ],
            'correlation_id' => CorrelationId::current($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back()->with('status', __('Invitation removed.'));
    }
}
