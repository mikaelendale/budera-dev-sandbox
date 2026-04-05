import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Trash } from '@phosphor-icons/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type AgentRow = {
    token_id: string;
    client_name: string;
    scopes: string[];
    created_at: string | null;
    expires_at: string | null;
    wallet_public_id: string | null;
    wallet_balance_cents: number | null;
    wallet_status: string | null;
    total_spent_cents: number;
};

function formatUsdFromCents(cents: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

export default function UserAgentsIndex() {
    const page = usePage<{ agents: AgentRow[] }>();
    const { agents } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'My Agents', href: '/my-agents' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Agents" />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <Heading
                    variant="small"
                    title="My Agents"
                    description="AI agents you've authorized to access your Budera wallet."
                />

                {agents.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No agents authorized yet. When you authorize an AI agent
                        via OAuth, it will appear here.
                    </p>
                ) : (
                    <div className="divide-y divide-border rounded-lg border border-border">
                        {agents.map((agent) => (
                            <div
                                key={agent.token_id}
                                className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0 space-y-1.5">
                                    <p className="font-medium text-foreground">
                                        {agent.client_name}
                                    </p>
                                    {agent.scopes.length > 0 && (
                                        <div className="flex flex-wrap gap-1">
                                            {agent.scopes.map((scope) => (
                                                <Badge
                                                    key={scope}
                                                    variant="secondary"
                                                >
                                                    {scope}
                                                </Badge>
                                            ))}
                                        </div>
                                    )}
                                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        {agent.wallet_balance_cents != null && (
                                            <span>
                                                Balance:{' '}
                                                {formatUsdFromCents(
                                                    agent.wallet_balance_cents,
                                                )}
                                            </span>
                                        )}
                                        <span>
                                            Spent:{' '}
                                            {formatUsdFromCents(
                                                agent.total_spent_cents,
                                            )}
                                        </span>
                                        {agent.wallet_status && (
                                            <span>
                                                Wallet: {agent.wallet_status}
                                            </span>
                                        )}
                                        {agent.created_at && (
                                            <span>
                                                Authorized{' '}
                                                {new Date(
                                                    agent.created_at,
                                                ).toLocaleDateString()}
                                            </span>
                                        )}
                                    </div>
                                </div>

                                <div className="flex shrink-0 items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        asChild
                                    >
                                        <Link
                                            href={`/my-agents/${agent.token_id}`}
                                            prefetch
                                        >
                                            View Details
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
                                            Revoke
                                        </Button>
                                    </Form>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
