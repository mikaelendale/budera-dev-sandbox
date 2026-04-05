import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Buildings, LinkBreak, ShieldCheck, Wallet } from '@phosphor-icons/react';
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
import userRoutes from '@/routes/user';
import type { BreadcrumbItem } from '@/types';

type WalletBankLink = {
    id: string;
    bank_slug: string | null;
    status: string;
    account_last4: string | null;
    verified_at: string | null;
};

type WalletProps = {
    public_id: string;
    balance_cents: number;
    status: string;
    environment: string;
    partner_account_id: string | null;
    bank_links: WalletBankLink[];
} | null;

type CompanyAccessRow = {
    company_name: string;
    connection_count: number;
    scopes: string[];
    latest_authorized_at: string | null;
};

type ConnectionRow = {
    token_id: string;
    client_name: string;
    company_name: string | null;
    scopes: string[];
    authorized_at: string | null;
    expires_at: string | null;
    has_wallet_access: boolean;
};

type WalletPageProps = {
    wallet: WalletProps;
    companyAccess: CompanyAccessRow[];
    connections: ConnectionRow[];
};

function formatUsdFromCents(cents: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

export default function UserWalletIndex() {
    const { wallet, companyAccess, connections } = usePage<WalletPageProps>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'My Wallet', href: '/my-wallet' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Wallet" />

            <div className="mx-auto w-full max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <Heading
                    variant="small"
                    title="My Wallet"
                    description="Your personal wallet, linked bank accounts, and AI companies authorized through OAuth."
                />

                {wallet === null ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Wallet not available</CardTitle>
                            <CardDescription>
                                We could not find a personal wallet for your account.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>Current balance</CardDescription>
                                    <CardTitle className="text-2xl tabular-nums">
                                        {formatUsdFromCents(wallet.balance_cents)}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>Wallet status</CardDescription>
                                    <CardTitle className="text-2xl capitalize">
                                        {wallet.status}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="pt-0 text-xs text-muted-foreground">
                                    {wallet.environment}
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>
                                        AI app connections
                                    </CardDescription>
                                    <CardTitle className="text-2xl">
                                        {connections.length}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Wallet className="size-4" />
                                    Wallet identity
                                </CardTitle>
                                <CardDescription>
                                    This wallet is shared across AI companies you
                                    authorize.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-1 text-sm text-muted-foreground">
                                <p className="font-mono text-xs text-foreground">
                                    {wallet.public_id}
                                </p>
                                {wallet.partner_account_id ? (
                                    <p>
                                        Partner account:{' '}
                                        <span className="font-mono">
                                            {wallet.partner_account_id}
                                        </span>
                                    </p>
                                ) : null}
                            </CardContent>
                        </Card>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        Linked bank accounts
                                    </CardTitle>
                                    <CardDescription>
                                        Accounts connected to this wallet.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {wallet.bank_links.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No linked bank accounts.
                                        </p>
                                    ) : (
                                        <ul className="divide-y divide-border rounded-lg border border-border">
                                            {wallet.bank_links.map((link) => (
                                                <li
                                                    key={link.id}
                                                    className="space-y-1 px-3 py-2"
                                                >
                                                    <div className="flex items-center justify-between gap-2">
                                                        <p className="font-medium capitalize">
                                                            {(
                                                                link.bank_slug ??
                                                                'unknown bank'
                                                            ).replace(
                                                                /[-_]/g,
                                                                ' ',
                                                            )}
                                                        </p>
                                                        <Badge
                                                            variant="outline"
                                                        >
                                                            {link.status}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground">
                                                        ****
                                                        {link.account_last4 ??
                                                            '----'}
                                                    </p>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Buildings className="size-4" />
                                        AI company access
                                    </CardTitle>
                                    <CardDescription>
                                        Companies currently authorized to use
                                        this wallet through OAuth.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {companyAccess.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No AI company currently has wallet
                                            access.
                                        </p>
                                    ) : (
                                        <ul className="space-y-3">
                                            {companyAccess.map((company) => (
                                                <li
                                                    key={company.company_name}
                                                    className="rounded-lg border border-border px-3 py-2"
                                                >
                                                    <div className="flex items-center justify-between gap-2">
                                                        <p className="font-medium">
                                                            {
                                                                company.company_name
                                                            }
                                                        </p>
                                                        <Badge
                                                            variant="secondary"
                                                        >
                                                            {
                                                                company.connection_count
                                                            }{' '}
                                                            app
                                                            {company.connection_count >
                                                            1
                                                                ? 's'
                                                                : ''}
                                                        </Badge>
                                                    </div>
                                                    {company.scopes.length >
                                                    0 ? (
                                                        <div className="mt-2 flex flex-wrap gap-1">
                                                            {company.scopes.map(
                                                                (scope) => (
                                                                    <Badge
                                                                        key={`${company.company_name}-${scope}`}
                                                                        variant="outline"
                                                                    >
                                                                        {scope}
                                                                    </Badge>
                                                                ),
                                                            )}
                                                        </div>
                                                    ) : null}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ShieldCheck className="size-4" />
                            Authorized integrations
                        </CardTitle>
                        <CardDescription>
                            Active OAuth authorizations currently mapped to your
                            wallet.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {connections.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No active OAuth integrations yet.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border rounded-lg border border-border">
                                {connections.map((connection) => (
                                    <li
                                        key={connection.token_id}
                                        className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="space-y-1">
                                            <p className="font-medium">
                                                {connection.client_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {connection.company_name ??
                                                    'Independent integration'}
                                            </p>
                                            <div className="flex flex-wrap gap-1">
                                                {connection.scopes.map(
                                                    (scope) => (
                                                        <Badge
                                                            key={`${connection.token_id}-${scope}`}
                                                            variant="outline"
                                                        >
                                                            {scope}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={`/my-agents/${connection.token_id}`}
                                                    prefetch
                                                >
                                                    Details
                                                </Link>
                                            </Button>
                                            <Form
                                                action={`/settings/oauth-connections/${connection.token_id}`}
                                                method="delete"
                                            >
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    size="sm"
                                                    className="text-destructive"
                                                >
                                                    <LinkBreak className="mr-1.5 size-4" />
                                                    Revoke
                                                </Button>
                                            </Form>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <div className="mt-4">
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={userRoutes.agents.index.url()} prefetch>
                                    View all agent sessions
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
