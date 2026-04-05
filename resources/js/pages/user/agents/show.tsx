import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Trash } from '@phosphor-icons/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type AgentProps = {
    token_id: string;
    client_name: string;
    scopes: string[];
    created_at: string | null;
    expires_at: string | null;
};

type WalletProps = {
    public_id: string;
    balance_cents: number;
    status: string;
    environment: string;
} | null;

type PolicyProps = {
    per_tx_limit_usd: string | number | null;
    daily_spend_limit_usd: string | number | null;
    daily_tx_count: number | null;
    allowed_categories: string[] | null;
} | null;

type LedgerRow = {
    id: number;
    type: string;
    amount_cents: number;
    reference_type: string | null;
    description: string | null;
    balance_after_cents: number;
    created_at: string | null;
};

function formatUsdFromCents(cents: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

export default function UserAgentShow() {
    const page = usePage<{
        agent: AgentProps;
        wallet: WalletProps;
        policy: PolicyProps;
        ledgerEntries: LedgerRow[];
    }>();
    const { agent, wallet, policy, ledgerEntries } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'My Agents', href: '/my-agents' },
        { title: agent.client_name, href: `/my-agents/${agent.token_id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={agent.client_name} />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Heading
                            variant="small"
                            title={agent.client_name}
                            description={
                                agent.expires_at
                                    ? `Expires ${new Date(agent.expires_at).toLocaleDateString()}`
                                    : undefined
                            }
                        />
                        {agent.scopes.length > 0 && (
                            <div className="flex flex-wrap gap-1">
                                {agent.scopes.map((scope) => (
                                    <Badge key={scope} variant="secondary">
                                        {scope}
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/my-agents" prefetch>
                                All agents
                            </Link>
                        </Button>
                        <Form
                            action={`/settings/oauth-connections/${agent.token_id}`}
                            method="delete"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                className="text-destructive"
                            >
                                <Trash className="mr-1.5 size-4" />
                                Revoke Access
                            </Button>
                        </Form>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Wallet</CardTitle>
                            <CardDescription>
                                Agent wallet balance and status
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {wallet === null ? (
                                <p className="text-sm text-muted-foreground">
                                    No wallet linked to this agent.
                                </p>
                            ) : (
                                <div className="space-y-2">
                                    <p className="text-2xl font-semibold tabular-nums">
                                        {formatUsdFromCents(
                                            wallet.balance_cents,
                                        )}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {wallet.status} · {wallet.environment}
                                    </p>
                                    <p className="font-mono text-xs text-muted-foreground">
                                        {wallet.public_id}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Spend Policy</CardTitle>
                            <CardDescription>
                                Controls and limits for this agent
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {policy === null ? (
                                <p className="text-muted-foreground">
                                    No policy configured.
                                </p>
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
                                    <p>
                                        Daily tx count:{' '}
                                        {policy.daily_tx_count ?? '—'}
                                    </p>
                                    {policy.allowed_categories &&
                                        policy.allowed_categories.length > 0 && (
                                            <div className="flex flex-wrap gap-1">
                                                {policy.allowed_categories.map(
                                                    (cat) => (
                                                        <Badge
                                                            key={cat}
                                                            variant="outline"
                                                        >
                                                            {cat}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold">
                        Transaction History
                    </h2>
                    {ledgerEntries.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No transactions yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                            {ledgerEntries.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex flex-col gap-1 px-3 py-2 sm:flex-row sm:justify-between"
                                >
                                    <span className="font-mono text-xs">
                                        #{row.id}
                                    </span>
                                    <span>{row.type}</span>
                                    <span className="tabular-nums">
                                        {formatUsdFromCents(row.amount_cents)}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {row.reference_type ?? '—'}
                                    </span>
                                    <span className="tabular-nums text-muted-foreground">
                                        bal{' '}
                                        {formatUsdFromCents(
                                            row.balance_after_cents,
                                        )}
                                    </span>
                                    {row.created_at ? (
                                        <time
                                            className="text-xs text-muted-foreground"
                                            dateTime={row.created_at}
                                        >
                                            {new Date(
                                                row.created_at,
                                            ).toLocaleString()}
                                        </time>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
