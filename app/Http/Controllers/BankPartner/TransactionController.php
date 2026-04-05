<?php

namespace App\Http\Controllers\BankPartner;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = LedgerEntry::query()
            ->with('walletAccount')
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->input('to_date'));
        }

        $entries = $query->paginate(50)->through(fn ($entry) => [
            'id' => $entry->id,
            'type' => $entry->type,
            'amount_cents' => (int) $entry->amount_cents,
            'reference_type' => $entry->reference_type,
            'reference_id' => $entry->reference_id,
            'balance_after_cents' => (int) $entry->balance_after_cents,
            'description' => $entry->description,
            'created_at' => $entry->created_at?->toIso8601String(),
            'wallet_public_id' => $entry->walletAccount?->public_id,
            'company_id' => $entry->walletAccount?->company_id,
        ]);

        return Inertia::render('bank-partner/transactions', [
            'entries' => $entries,
            'filters' => $request->only(['type', 'from_date', 'to_date']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = LedgerEntry::query()
            ->with('walletAccount')
            ->orderByDesc('created_at');

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->input('to_date'));
        }

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Type', 'Amount (cents)', 'Reference Type', 'Reference ID', 'Balance After (cents)', 'Description', 'Wallet', 'Created At']);

            $query->chunk(500, function ($entries) use ($handle): void {
                foreach ($entries as $entry) {
                    fputcsv($handle, [
                        $entry->id,
                        $entry->type,
                        $entry->amount_cents,
                        $entry->reference_type,
                        $entry->reference_id,
                        $entry->balance_after_cents,
                        $entry->description,
                        $entry->walletAccount?->public_id,
                        $entry->created_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, 'transactions-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
