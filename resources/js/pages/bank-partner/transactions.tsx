import { Head, router, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import bankPartner from '@/routes/bank-partner';
import type { BreadcrumbItem } from '@/types';

type EntryRow = {
    id: number;
    type: string;
    amount_cents: number;
    reference_type: string;
    reference_id: string;
    balance_after_cents: number;
    description: string | null;
    created_at: string | null;
    wallet_public_id: string | null;
    company_id: number | null;
};

type PaginatedEntries = {
    data: EntryRow[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type Filters = {
    type?: string;
    from_date?: string;
    to_date?: string;
};

function formatUsdFromCents(cents: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Bank partner', href: bankPartner.dashboard.url() },
    { title: 'Transactions', href: bankPartner.transactions.index.url() },
];

export default function BankPartnerTransactions() {
    const { entries, filters } = usePage<{
        entries: PaginatedEntries;
        filters: Filters;
    }>().props;

    const [type, setType] = useState(filters.type ?? '');
    const [fromDate, setFromDate] = useState(filters.from_date ?? '');
    const [toDate, setToDate] = useState(filters.to_date ?? '');

    function applyFilters() {
        const params: Record<string, string> = {};
        if (type) params.type = type;
        if (fromDate) params.from_date = fromDate;
        if (toDate) params.to_date = toDate;

        router.get(bankPartner.transactions.index.url(), params, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function exportUrl(): string {
        const params = new URLSearchParams();
        if (fromDate) params.set('from_date', fromDate);
        if (toDate) params.set('to_date', toDate);
        const qs = params.toString();
        return bankPartner.transactions.export.url() + (qs ? `?${qs}` : '');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bank partner — Transactions" />

            <div className="mx-auto max-w-6xl space-y-6 px-4 py-6 md:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Transactions"
                        description="All ledger entries across companies."
                    />
                    <Button variant="outline" size="sm" asChild>
                        <a href={exportUrl()} download>
                            <Download className="mr-1.5 size-4" />
                            Export CSV
                        </a>
                    </Button>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="w-36">
                        <Select value={type} onValueChange={setType}>
                            <SelectTrigger>
                                <SelectValue placeholder="All types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="credit">Credit</SelectItem>
                                <SelectItem value="debit">Debit</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Input
                        type="date"
                        className="w-40"
                        value={fromDate}
                        onChange={(e) => setFromDate(e.target.value)}
                        placeholder="From date"
                    />
                    <Input
                        type="date"
                        className="w-40"
                        value={toDate}
                        onChange={(e) => setToDate(e.target.value)}
                        placeholder="To date"
                    />
                    <Button size="sm" onClick={applyFilters}>
                        Filter
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/50 text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2">ID</th>
                                <th className="px-3 py-2">Type</th>
                                <th className="px-3 py-2">Amount</th>
                                <th className="px-3 py-2">Ref type</th>
                                <th className="px-3 py-2">Balance after</th>
                                <th className="px-3 py-2">Wallet</th>
                                <th className="px-3 py-2">Created</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {entries.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No transactions found.
                                    </td>
                                </tr>
                            ) : (
                                entries.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {row.id}
                                        </td>
                                        <td className="px-3 py-2">
                                            <span
                                                className={
                                                    row.type === 'credit'
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : 'text-red-600 dark:text-red-400'
                                                }
                                            >
                                                {row.type}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {formatUsdFromCents(row.amount_cents)}
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {row.reference_type}
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {formatUsdFromCents(row.balance_after_cents)}
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {row.wallet_public_id ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {row.created_at
                                                ? new Date(row.created_at).toLocaleString()
                                                : '—'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {entries.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Page {entries.current_page} of {entries.last_page}
                        </span>
                        <div className="flex gap-2">
                            {entries.prev_page_url && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(entries.prev_page_url!, {}, { preserveState: true })
                                    }
                                >
                                    Previous
                                </Button>
                            )}
                            {entries.next_page_url && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(entries.next_page_url!, {}, { preserveState: true })
                                    }
                                >
                                    Next
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
