import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import company from '@/routes/company';
import type { BreadcrumbItem } from '@/types';

type WalletProps = {
    public_id: string;
    status: string;
    balance_cents: number;
    environment: string;
    agent_id: string | null;
    metadata: Record<string, unknown> | null;
};

type PolicySummary = {
    per_tx_limit_usd: string | number | null;
    daily_spend_limit_usd: string | number | null;
    velocity_sensitivity: string;
} | null;

type KycRow = {
    status: string;
    updated_at: string | null;
} | null;

type LedgerRow = {
    id: number;
    type: string;
    amount_cents: number;
    balance_after_cents: number;
    description: string | null;
    created_at: string | null;
};

type TransitionRow = {
    id: number;
    from_state: string;
    to_state: string;
    actor_type: string | null;
    actor_id: string | null;
    created_at: string | null;
};

function formatUsdFromCents(cents: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

export default function CompanyWalletShow() {
    const page = usePage<{
        wallet: WalletProps;
        policy: PolicySummary;
        latestKyc: KycRow;
        canManagePolicy: boolean;
        ledgerEntries: LedgerRow[];
        ledgerPagination: { next_url: string | null; prev_url: string | null };
        stateTransitions: TransitionRow[];
        stateTransitionsPagination: { next_url: string | null; prev_url: string | null };
    }>();

    const {
        wallet,
        policy,
        latestKyc,
        canManagePolicy,
        ledgerEntries,
        ledgerPagination,
        stateTransitions,
        stateTransitionsPagination,
    } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Wallets', href: company.wallets.index.url() },
        { title: wallet.public_id, href: company.wallets.show.url(wallet.public_id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Wallet ${wallet.public_id}`} />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title={wallet.public_id}
                        description={`${wallet.status} · ${wallet.environment}`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={company.wallets.index.url()} prefetch>
                                All wallets
                            </Link>
                        </Button>
                        {(canManagePolicy || policy !== null) && (
                            <Button size="sm" asChild>
                                <Link
                                    href={company.wallets.policy.show.url(wallet.public_id)}
                                    prefetch
                                >
                                    Spend policy
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Balance</CardTitle>
                            <CardDescription>Cached ledger balance</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold tabular-nums">
                                {formatUsdFromCents(wallet.balance_cents)}
                            </p>
                            {wallet.agent_id ? (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Agent: {wallet.agent_id}
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>KYC</CardTitle>
                            <CardDescription>Latest verification</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {latestKyc === null ? (
                                <p className="text-sm text-muted-foreground">No KYC record yet.</p>
                            ) : (
                                <div className="text-sm">
                                    <p className="font-medium">{latestKyc.status}</p>
                                    {latestKyc.updated_at ? (
                                        <p className="text-muted-foreground">
                                            Updated {new Date(latestKyc.updated_at).toLocaleString()}
                                        </p>
                                    ) : null}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Policy summary</CardTitle>
                        <CardDescription>High-level spend controls</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        {policy === null ? (
                            <p className="text-muted-foreground">No policy configured.</p>
                        ) : (
                            <>
                                <p>
                                    Per-tx limit:{' '}
                                    {policy.per_tx_limit_usd != null
                                        ? `$${policy.per_tx_limit_usd}`
                                        : '—'}
                                </p>
                                <p>
                                    Daily spend:{' '}
                                    {policy.daily_spend_limit_usd != null
                                        ? `$${policy.daily_spend_limit_usd}`
                                        : '—'}
                                </p>
                                <p>Velocity: {policy.velocity_sensitivity}</p>
                            </>
                        )}
                    </CardContent>
                </Card>

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold">Ledger</h2>
                    {ledgerEntries.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No ledger entries yet.</p>
                    ) : (
                        <>
                            <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                                {ledgerEntries.map((row) => (
                                    <li
                                        key={row.id}
                                        className="flex flex-col gap-1 px-3 py-2 sm:flex-row sm:justify-between"
                                    >
                                        <span className="font-mono text-xs">#{row.id}</span>
                                        <span>{row.type}</span>
                                        <span className="tabular-nums">{row.amount_cents} ¢</span>
                                        <span className="tabular-nums text-muted-foreground">
                                            after {row.balance_after_cents} ¢
                                        </span>
                                        {row.created_at ? (
                                            <time
                                                className="text-xs text-muted-foreground"
                                                dateTime={row.created_at}
                                            >
                                                {new Date(row.created_at).toLocaleString()}
                                            </time>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                            <div className="flex gap-3 text-sm">
                                {ledgerPagination.prev_url ? (
                                    <Link
                                        className="text-primary underline-offset-4 hover:underline"
                                        href={ledgerPagination.prev_url}
                                        preserveState
                                    >
                                        Previous
                                    </Link>
                                ) : null}
                                {ledgerPagination.next_url ? (
                                    <Link
                                        className="text-primary underline-offset-4 hover:underline"
                                        href={ledgerPagination.next_url}
                                        preserveState
                                    >
                                        Next
                                    </Link>
                                ) : null}
                            </div>
                        </>
                    )}
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold">State history</h2>
                    {stateTransitions.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No transitions recorded.</p>
                    ) : (
                        <>
                            <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                                {stateTransitions.map((row) => (
                                    <li
                                        key={row.id}
                                        className="flex flex-col gap-1 px-3 py-2 sm:flex-row sm:flex-wrap sm:justify-between"
                                    >
                                        <span className="font-mono text-xs">#{row.id}</span>
                                        <span>
                                            {row.from_state} → {row.to_state}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {row.actor_type ?? '—'}
                                            {row.actor_id ? ` · ${row.actor_id}` : ''}
                                        </span>
                                        {row.created_at ? (
                                            <time
                                                className="text-xs text-muted-foreground"
                                                dateTime={row.created_at}
                                            >
                                                {new Date(row.created_at).toLocaleString()}
                                            </time>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                            <div className="flex gap-3 text-sm">
                                {stateTransitionsPagination.prev_url ? (
                                    <Link
                                        className="text-primary underline-offset-4 hover:underline"
                                        href={stateTransitionsPagination.prev_url}
                                        preserveState
                                    >
                                        Previous
                                    </Link>
                                ) : null}
                                {stateTransitionsPagination.next_url ? (
                                    <Link
                                        className="text-primary underline-offset-4 hover:underline"
                                        href={stateTransitionsPagination.next_url}
                                        preserveState
                                    >
                                        Next
                                    </Link>
                                ) : null}
                            </div>
                        </>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
