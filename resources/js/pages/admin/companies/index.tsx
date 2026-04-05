import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

type CompanyRow = {
    id: number;
    name: string;
    email: string | null;
    kyb_status: string;
    live_enabled_at: string | null;
    owner_email: string | null;
    wallet_accounts_count: number;
    total_balance_cents: number;
};

function formatUsdFromCents(cents: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

export default function AdminCompaniesIndex() {
    const page = usePage<{ companies: CompanyRow[] }>();
    const { companies } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Companies', href: admin.companies.index.url() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Companies" />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <Heading
                    variant="small"
                    title="Companies"
                    description="Directory, balances, and KYB / live status."
                />

                <div className="space-y-3">
                    {companies.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No companies.</p>
                    ) : (
                        companies.map((row) => (
                            <div
                                key={row.id}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-4"
                            >
                                <div className="min-w-0 space-y-1">
                                    <p className="font-medium text-foreground">{row.name}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {row.owner_email ?? row.email ?? '—'} · KYB{' '}
                                        <span className="font-mono text-xs">{row.kyb_status}</span>
                                        {row.live_enabled_at ? (
                                            <span className="ml-2 rounded bg-emerald-500/15 px-1.5 py-0.5 text-xs text-emerald-800 dark:text-emerald-200">
                                                Live
                                            </span>
                                        ) : (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                Not live
                                            </span>
                                        )}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {row.wallet_accounts_count} wallet(s) ·{' '}
                                        {formatUsdFromCents(row.total_balance_cents)} total balance
                                    </p>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={admin.companies.show.url(row.id)} prefetch>
                                        View
                                    </Link>
                                </Button>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
