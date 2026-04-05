<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComplianceFlag;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComplianceFlagController extends Controller
{
    public function index(): Response
    {
        $flags = ComplianceFlag::query()
            ->whereNull('resolved_at')
            ->with([
                'flaggable' => function ($morphTo): void {
                    $morphTo->morphWith([
                        Payment::class => ['walletAccount'],
                    ]);
                },
            ])
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (ComplianceFlag $f): array {
                $paymentPublicId = null;
                $walletPublicId = null;
                if ($f->flaggable instanceof Payment) {
                    $paymentPublicId = $f->flaggable->public_id;
                    $walletPublicId = $f->flaggable->walletAccount?->public_id;
                }

                return [
                    'id' => $f->id,
                    'flag_type' => $f->flag_type,
                    'severity' => $f->severity,
                    'created_at' => $f->created_at?->toIso8601String(),
                    'payment_public_id' => $paymentPublicId,
                    'wallet_public_id' => $walletPublicId,
                ];
            });

        return Inertia::render('admin/compliance/index', [
            'flags' => $flags,
        ]);
    }

    public function show(ComplianceFlag $complianceFlag): Response
    {
        $complianceFlag->load([
            'flaggable' => function ($morphTo): void {
                $morphTo->morphWith([
                    Payment::class => ['walletAccount'],
                ]);
            },
        ]);

        $payment = $complianceFlag->flaggable instanceof Payment
            ? $complianceFlag->flaggable
            : null;

        return Inertia::render('admin/compliance/show', [
            'flag' => [
                'id' => $complianceFlag->id,
                'flag_type' => $complianceFlag->flag_type,
                'severity' => $complianceFlag->severity,
                'details' => $complianceFlag->details,
                'resolved_at' => $complianceFlag->resolved_at?->toIso8601String(),
                'resolved_by' => $complianceFlag->resolved_by,
                'created_at' => $complianceFlag->created_at?->toIso8601String(),
                'payment' => $payment === null ? null : [
                    'public_id' => $payment->public_id,
                    'status' => (string) $payment->status,
                    'amount_usd' => $payment->amount_usd !== null ? (string) $payment->amount_usd : null,
                    'wallet_public_id' => $payment->walletAccount?->public_id,
                ],
            ],
        ]);
    }

    public function resolve(Request $request, ComplianceFlag $complianceFlag): RedirectResponse
    {
        if ($complianceFlag->resolved_at !== null) {
            return redirect()->route('admin.compliance.show', $complianceFlag)
                ->with('status', __('Flag was already resolved.'));
        }

        $complianceFlag->forceFill([
            'resolved_at' => now(),
            'resolved_by' => $request->user()->getKey(),
        ])->save();

        return redirect()->route('admin.compliance.index')
            ->with('status', __('Compliance flag resolved.'));
    }
}
