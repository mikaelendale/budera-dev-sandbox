import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowSquareOut,
    Bell,
    BookOpenText,
    Check,
    ClipboardText,
    Copy,
    Key,
    ShieldCheck,
    UsersThree,
} from '@phosphor-icons/react';
import { useState } from 'react';
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
import { dashboard } from '@/routes';
import company from '@/routes/company';
import docs from '@/routes/docs';
import type { BreadcrumbItem } from '@/types';

type DeliveryRow = {
    id: number;
    event: string;
    status: string;
    attempts: number;
    response_status: number | null;
    last_attempted_at: string | null;
};

type DashboardProps = {
    walletCount: number;
    activeKeyCount: number;
    activeKeyPreview: string | null;
    recentWebhookDeliveries: DeliveryRow[];
    kybStatus: string;
    liveEnabledAt: string | null;
    companyName: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

function kybBadgeVariant(status: string) {
    switch (status) {
        case 'approved':
            return 'default' as const;
        case 'pending':
        case 'under_review':
            return 'outline' as const;
        case 'rejected':
            return 'destructive' as const;
        default:
            return 'secondary' as const;
    }
}

function kybBadgeClassName(status: string) {
    switch (status) {
        case 'approved':
            return 'bg-emerald-600/15 text-emerald-700 dark:text-emerald-400 border-transparent';
        case 'pending':
        case 'under_review':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-transparent';
        case 'rejected':
            return '';
        default:
            return '';
    }
}

function deliveryBadgeClassName(status: string) {
    switch (status) {
        case 'delivered':
            return 'bg-emerald-600/15 text-emerald-700 dark:text-emerald-400 border-transparent';
        case 'failed':
            return '';
        default:
            return '';
    }
}

function CopyButton({ text }: { text: string }) {
    const [copied, setCopied] = useState(false);

    function handleCopy() {
        navigator.clipboard.writeText(text).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    return (
        <Button
            variant="ghost"
            size="sm"
            className="h-7 w-7 shrink-0"
            onClick={handleCopy}
        >
            {copied ? (
                <Check className="size-3.5" weight="bold" />
            ) : (
                <Copy className="size-3.5" />
            )}
            <span className="sr-only">Copy API key</span>
        </Button>
    );
}

function WebhookSkeleton() {
    return (
        <div className="animate-pulse space-y-3">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center justify-between gap-2">
                    <div className="h-4 w-2/3 rounded bg-muted" />
                    <div className="h-4 w-16 rounded bg-muted" />
                </div>
            ))}
        </div>
    );
}

function WebhookList({ deliveries }: { deliveries: DeliveryRow[] }) {
    if (deliveries.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No webhook deliveries yet.
            </p>
        );
    }

    return (
        <ul className="divide-y divide-border">
            {deliveries.map((d) => (
                <li
                    key={d.id}
                    className="flex items-start justify-between gap-2 py-2.5 first:pt-0 last:pb-0"
                >
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-mono text-xs">
                            {d.event}
                        </p>
                        {d.last_attempted_at && (
                            <time
                                className="text-xs text-muted-foreground"
                                dateTime={d.last_attempted_at}
                            >
                                {new Date(d.last_attempted_at).toLocaleString()}
                            </time>
                        )}
                    </div>
                    <Badge
                        variant={d.status === 'failed' ? 'destructive' : 'secondary'}
                        className={deliveryBadgeClassName(d.status)}
                    >
                        {d.status}
                    </Badge>
                </li>
            ))}
        </ul>
    );
}

function DeferredWebhooks() {
    const { recentWebhookDeliveries } = usePage<DashboardProps>().props;
    return <WebhookList deliveries={recentWebhookDeliveries} />;
}

const quickLinks = [
    { title: 'API Keys', href: company.apiKeys.index.url(), icon: Key },
    { title: 'Webhooks', href: company.webhooks.index.url(), icon: Bell },
    { title: 'OAuth Apps', href: company.oauthApps.index.url(), icon: ShieldCheck },
    { title: 'Docs', href: docs.index.url(), icon: BookOpenText },
    { title: 'Team', href: company.team.url(), icon: UsersThree },
];

export default function Dashboard() {
    const {
        walletCount,
        activeKeyCount,
        activeKeyPreview,
        kybStatus,
        liveEnabledAt,
        companyName,
    } = usePage<DashboardProps>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <Heading
                    variant="small"
                    title={`Welcome back${companyName ? `, ${companyName}` : ''}`}
                    description="Your integration overview and quick actions."
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Left column */}
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        {/* Stats row */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>End-user wallets</CardDescription>
                                    <CardTitle className="text-2xl tabular-nums">
                                        {walletCount}
                                    </CardTitle>
                                </CardHeader>
                            </Card>

                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>Active API keys</CardDescription>
                                    <CardTitle className="text-2xl tabular-nums">
                                        {activeKeyCount}
                                    </CardTitle>
                                </CardHeader>
                            </Card>

                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>KYB Status</CardDescription>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={kybBadgeVariant(kybStatus)}
                                            className={kybBadgeClassName(kybStatus)}
                                        >
                                            {kybStatus.replace(/_/g, ' ')}
                                        </Badge>
                                    </div>
                                    {liveEnabledAt && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Live since{' '}
                                            {new Date(liveEnabledAt).toLocaleDateString()}
                                        </p>
                                    )}
                                </CardHeader>
                            </Card>
                        </div>

                        {/* API Key Quick Access */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Key className="size-4" weight="bold" />
                                    API Key Quick Access
                                </CardTitle>
                                <CardDescription>
                                    Your sandbox API key for integration testing.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {activeKeyPreview ? (
                                    <div className="flex items-center gap-2">
                                        <code className="flex-1 rounded-md border bg-muted/50 px-3 py-2 font-mono text-sm">
                                            {activeKeyPreview}
                                        </code>
                                        <CopyButton text={activeKeyPreview} />
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No active sandbox API key.{' '}
                                        <Link
                                            className="text-primary underline-offset-4 hover:underline"
                                            href={company.apiKeys.index.url()}
                                            prefetch
                                        >
                                            Create one
                                        </Link>{' '}
                                        to get started.
                                    </p>
                                )}
                                <div className="mt-3">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={company.apiKeys.index.url()} prefetch>
                                            Manage API keys
                                            <ArrowSquareOut className="ml-1.5 size-3.5" />
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Quick Links */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ClipboardText className="size-4" weight="bold" />
                                    Quick Links
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-5">
                                    {quickLinks.map((link) => (
                                        <Button
                                            key={link.title}
                                            variant="outline"
                                            size="sm"
                                            className="justify-start gap-2"
                                            asChild
                                        >
                                            <Link href={link.href} prefetch>
                                                <link.icon className="size-4" />
                                                {link.title}
                                            </Link>
                                        </Button>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right column */}
                    <div className="lg:col-span-1">
                        <Card className="h-full">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Bell className="size-4" weight="bold" />
                                    Recent Webhook Events
                                </CardTitle>
                                <CardDescription>
                                    Latest delivery attempts for your endpoints.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Deferred
                                    data="recentWebhookDeliveries"
                                    fallback={<WebhookSkeleton />}
                                >
                                    <DeferredWebhooks />
                                </Deferred>
                                <div className="mt-4 border-t pt-3">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="w-full"
                                        asChild
                                    >
                                        <Link
                                            href={company.webhooks.index.url()}
                                            prefetch
                                        >
                                            View all webhooks
                                            <ArrowSquareOut className="ml-1.5 size-3.5" />
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
