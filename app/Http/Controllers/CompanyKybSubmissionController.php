<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyKybSubmissionRequest;
use App\Models\KybReview;
use App\Services\Audit\AuditService;
use App\Services\Audit\CorrelationId;
use App\Services\KybService;
use App\States\KybReview\KybReviewPending;
use App\States\KybReview\KybReviewUnderReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CompanyKybSubmissionController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $company = $user?->firstCompany();

        if ($company === null) {
            abort(403);
        }

        abort_unless((int) $company->owner_id === (int) $user->id, 403);

        $hasOpenKyb = KybReview::query()
            ->where('company_id', $company->id)
            ->where(function ($q): void {
                $q->where('status', KybReviewPending::class)
                    ->orWhere('status', KybReviewUnderReview::class);
            })
            ->exists();

        $canSubmit = $company->live_enabled_at === null
            && ! $hasOpenKyb
            && (string) $company->kyb_status !== 'approved';

        if (! $canSubmit) {
            return redirect()->route('company.settings')
                ->with('status', __('You cannot submit a new KYB application right now.'));
        }

        return Inertia::render('company/kyb-submit', [
            'prefillEmail' => $user->email,
        ]);
    }

    public function store(StoreCompanyKybSubmissionRequest $request, KybService $kybService): RedirectResponse
    {
        $user = $request->user();
        $company = $user->firstCompany();
        if ($company === null) {
            abort(403, 'company_required');
        }

        abort_unless((int) $company->owner_id === (int) $user->id, 403);

        try {
            $review = $kybService->submitForReview(
                $company,
                $request->validated('questionnaire'),
                $request->file('document_government_id'),
                $request->file('document_certificate_incorporation'),
                $request->file('document_director_id'),
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->route('company.settings')
                ->withErrors(['kyb' => $e->getMessage()]);
        }

        $this->auditService->recordDomainAudit([
            'stream' => 'developer',
            'actor_type' => 'user',
            'actor_id' => (string) $user->getKey(),
            'action' => 'kyb.submitted_for_review',
            'resource_type' => 'kyb_reviews',
            'resource_id' => (string) $review->getKey(),
            'environment' => 'live',
            'metadata' => [
                'company_id' => (string) $company->getKey(),
            ],
            'correlation_id' => CorrelationId::current($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('company.settings')
            ->with('status', __('Your company has been submitted for KYB review.'));
    }
}
