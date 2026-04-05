<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KybReview;
use App\Services\KybService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class KybReviewController extends Controller
{
    public function index(): Response
    {
        $reviews = KybReview::query()
            ->with(['company.owner'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (KybReview $r): array => [
                'id' => $r->id,
                'company_id' => $r->company_id,
                'company_name' => $r->company?->name,
                'owner_email' => $r->company?->owner?->email,
                'environment' => $r->environment,
                'status' => $r->status->getValue(),
                'decided_at' => $r->decided_at?->toIso8601String(),
                'updated_at' => $r->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/kyb-reviews/index', [
            'reviews' => $reviews,
        ]);
    }

    public function show(KybReview $kybReview): Response
    {
        $kybReview->load(['company.owner']);

        return Inertia::render('admin/kyb-reviews/show', [
            'review' => [
                'id' => $kybReview->id,
                'company_id' => $kybReview->company_id,
                'company_name' => $kybReview->company?->name,
                'owner_email' => $kybReview->company?->owner?->email,
                'environment' => $kybReview->environment,
                'status' => $kybReview->status->getValue(),
                'decided_at' => $kybReview->decided_at?->toIso8601String(),
                'notes' => $kybReview->notes,
                'documents' => $kybReview->documents ?? [],
                'questionnaire' => $kybReview->questionnaire ?? null,
            ],
        ]);
    }

    public function startReview(Request $request, KybReview $kybReview, KybService $kybService): RedirectResponse
    {
        try {
            $kybService->startReview($kybReview, $request->user());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.kyb-reviews.show', $kybReview)
                ->withErrors(['kyb' => $e->getMessage()]);
        }

        return redirect()->route('admin.kyb-reviews.show', $kybReview)
            ->with('status', __('Review started.'));
    }

    public function approve(Request $request, KybReview $kybReview, KybService $kybService): RedirectResponse
    {
        try {
            $kybService->approve($kybReview, $request->user());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.kyb-reviews.show', $kybReview)
                ->withErrors(['kyb' => $e->getMessage()]);
        }

        return redirect()->route('admin.kyb-reviews.index')
            ->with('status', __('KYB approved.'));
    }

    public function reject(Request $request, KybReview $kybReview, KybService $kybService): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $kybService->reject($kybReview, $request->user(), $validated['reason']);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.kyb-reviews.show', $kybReview)
                ->withErrors(['kyb' => $e->getMessage()]);
        }

        return redirect()->route('admin.kyb-reviews.index')
            ->with('status', __('KYB rejected.'));
    }
}
