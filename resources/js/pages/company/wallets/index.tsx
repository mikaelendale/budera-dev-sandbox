import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import company from '@/routes/company';
import type { BreadcrumbItem } from '@/types';

type WalletRow = {
    public_id: string;
    status: string;
    balance_cents: number;
    environment: string;
    agent_id: string | null;
    has_policy: boolean;
};

function formatUsdFromCents(cents: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

export default function CompanyWalletsIndex() {
    const page = usePage<{ wallets: WalletRow[] }>();
    const { wallets } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Wallets', href: company.wallets.index.url() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Wallets" />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <Heading
                    variant="small"
                    title="Wallets"
                    description="Wallet accounts for the current dashboard environment (sandbox or live)."
                />

                {wallets.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No wallets yet.</p>
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                        {wallets.map((w) => (
                            <li
                                key={w.public_id}
                                className="flex flex-col gap-2 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <Link
                                        className="font-mono text-xs text-primary underline-offset-4 hover:underline"
                                        href={company.wallets.show.url(w.public_id)}
                                        prefetch
                                    >
                                        {w.public_id}
                                    </Link>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {w.status} · {w.environment}
                                        {w.has_policy ? ' · policy configured' : ''}
                                    </p>
                                    {w.agent_id ? (
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            Agent: {w.agent_id}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex flex-wrap items-center gap-3">
                                    <span className="text-base font-medium tabular-nums sm:text-sm">
                                        {formatUsdFromCents(w.balance_cents)}
                                    </span>
                                    <Link
                                        className="text-xs text-primary underline-offset-4 hover:underline"
                                        href={company.wallets.policy.show.url(w.public_id)}
                                        prefetch
                                    >
                                        Policy
                                    </Link>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
