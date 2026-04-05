import { Head, router, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import bankPartner from '@/routes/bank-partner';
import type { BreadcrumbItem } from '@/types';

type WalletRow = {
    public_id: string;
    company_id: number;
    cached_balance_cents: number;
    ledger_balance_cents: number;
    mismatch: boolean;
    environment: string;
};

type PaginatedWallets = {
    data: WalletRow[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

function formatUsdFromCents(cents: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Bank partner', href: bankPartner.dashboard.url() },
    { title: 'Reconciliation', href: bankPartner.reconciliation.index.url() },
];

export default function BankPartnerReconciliation() {
    const { wallets } = usePage<{ wallets: PaginatedWallets }>().props;

    const mismatchCount = wallets.data.filter((w) => w.mismatch).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bank partner — Reconciliation" />

            <div className="mx-auto max-w-6xl space-y-6 px-4 py-6 md:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Reconciliation"
                        description="Cached balance vs ledger-computed balance for active wallets."
                    />
                    {mismatchCount > 0 && (
                        <Badge variant="destructive">
                            {mismatchCount} mismatch{mismatchCount > 1 ? 'es' : ''}
                        </Badge>
                    )}
                </div>

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/50 text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2">Wallet</th>
                                <th className="px-3 py-2">Company</th>
                                <th className="px-3 py-2">Environment</th>
                                <th className="px-3 py-2 text-right">Cached balance</th>
                                <th className="px-3 py-2 text-right">Ledger balance</th>
                                <th className="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {wallets.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No active wallets found.
                                    </td>
                                </tr>
                            ) : (
                                wallets.data.map((row) => (
                                    <tr
                                        key={row.public_id}
                                        className={
                                            row.mismatch
                                                ? 'bg-destructive/5'
                                                : ''
                                        }
                                    >
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {row.public_id}
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {row.company_id}
                                        </td>
                                        <td className="px-3 py-2">{row.environment}</td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {formatUsdFromCents(row.cached_balance_cents)}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {formatUsdFromCents(row.ledger_balance_cents)}
                                        </td>
                                        <td className="px-3 py-2">
                                            {row.mismatch ? (
                                                <Badge variant="destructive">Mismatch</Badge>
                                            ) : (
                                                <Badge variant="outline">OK</Badge>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {wallets.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Page {wallets.current_page} of {wallets.last_page}
                        </span>
                        <div className="flex gap-2">
                            {wallets.prev_page_url && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(wallets.prev_page_url!, {}, { preserveState: true })
                                    }
                                >
                                    Previous
                                </Button>
                            )}
                            {wallets.next_page_url && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(wallets.next_page_url!, {}, { preserveState: true })
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
