<?php

namespace App\Http\Controllers\BankPartner;

use App\Http\Controllers\Controller;
use App\Models\KybReview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KybDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $reviews = KybReview::query()
            ->withoutGlobalScopes()
            ->with('company')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn ($review) => [
                'id' => $review->id,
                'company_name' => $review->company?->name,
                'status' => (string) $review->status,
                'created_at' => $review->created_at?->toIso8601String(),
                'updated_at' => $review->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('bank-partner/kyb-documents', [
            'reviews' => $reviews,
        ]);
    }
}
