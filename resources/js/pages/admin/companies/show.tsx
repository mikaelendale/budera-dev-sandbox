import { Form, Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

type CompanyHeader = {
    id: number;
    name: string;
    email: string | null;
    kyb_status: string;
    live_enabled_at: string | null;
    owner_email: string | null;
};

type MemberRow = {
    id: number;
    name: string;
    email: string;
    role: string;
};

type WalletUser = {
    id: number;
    name: string;
    email: string;
};

type WalletRow = {
    public_id: string;
    user_id: number | null;
    user: WalletUser | null;
    status: string;
    environment: string;
    balance_cents: number;
};

type ApiKeyRow = {
    id: number;
    environment: string;
    status: string;
    label: string | null;
    abilities: string[] | null;
    created_at: string | null;
};

type ActivityRow = {
    id: number;
    action: string;
    stream: string;
    actor_type: string;
    actor_id: string | null;
    environment: string | null;
    created_at: string | null;
};

function formatUsdFromCents(cents: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

export default function AdminCompanyShow() {
    const page = usePage<{
        company: CompanyHeader;
        members: MemberRow[];
        wallets: WalletRow[];
        apiKeys: ApiKeyRow[];
        activity: ActivityRow[];
        flash?: { status?: string | null };
    }>();
    const { company, members, wallets, apiKeys, activity, flash } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Companies', href: admin.companies.index.url() },
        { title: company.name, href: admin.companies.show.url(company.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={company.name} />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title={company.name}
                        description={`KYB ${company.kyb_status}${company.live_enabled_at ? ' · Live enabled' : ''}`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={admin.companies.index.url()}>Back</Link>
                        </Button>
                        <Form
                            action={admin.companies.freeze.url(company.id)}
                            method="post"
                            className="inline"
                            onSubmit={(e) => {
                                if (!window.confirm('Freeze all active/paused wallets for this company?')) {
                                    e.preventDefault();
                                }
                            }}
                        >
                            <Button type="submit" variant="destructive" size="sm">
                                Freeze wallets
                            </Button>
                        </Form>
                        <Form
                            action={admin.companies.unfreeze.url(company.id)}
                            method="post"
                            className="inline"
                            onSubmit={(e) => {
                                if (!window.confirm('Unfreeze all frozen wallets for this company?')) {
                                    e.preventDefault();
                                }
                            }}
                        >
                            <Button type="submit" variant="secondary" size="sm">
                                Unfreeze wallets
                            </Button>
                        </Form>
                    </div>
                </div>

                {flash?.status ? (
                    <p
                        className="rounded-lg border border-border bg-muted/50 px-3 py-2 text-sm"
                        role="status"
                    >
                        {flash.status}
                    </p>
                ) : null}

                <section className="space-y-2">
                    <h2 className="text-sm font-semibold">Members</h2>
                    <ul className="divide-y rounded-lg border border-border text-sm">
                        {members.map((m) => (
                            <li key={m.id} className="flex justify-between gap-2 px-3 py-2">
                                <span>
                                    {m.name} · {m.email}
                                </span>
                                <span className="font-mono text-xs text-muted-foreground">{m.role}</span>
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="space-y-2">
                    <h2 className="text-sm font-semibold">Wallets (end-user accounts)</h2>
                    <p className="text-xs text-muted-foreground">
                        Team members are listed under Members above; each wallet belongs to an end user.
                    </p>
                    <ul className="divide-y rounded-lg border border-border text-sm">
                        {wallets.length === 0 ? (
                            <li className="px-3 py-2 text-muted-foreground">No wallets.</li>
                        ) : (
                            wallets.map((w) => (
                                <li
                                    key={w.public_id}
                                    className="flex flex-col gap-1 px-3 py-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2"
                                >
                                    <div className="min-w-0 flex flex-col gap-0.5">
                                        <span className="font-mono text-xs">{w.public_id}</span>
                                        {w.user ? (
                                            <span className="text-xs">
                                                {w.user.name} · {w.user.email}{' '}
                                                <span className="font-mono text-muted-foreground">
                                                    #{w.user.id}
                                                </span>
                                            </span>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                No linked user
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-muted-foreground sm:shrink-0">
                                        <span>
                                            {w.status} · {w.environment}
                                        </span>
                                        <span className="tabular-nums text-foreground">
                                            {formatUsdFromCents(w.balance_cents)}
                                        </span>
                                    </div>
                                </li>
                            ))
                        )}
                    </ul>
                </section>

                <section className="space-y-2">
                    <h2 className="text-sm font-semibold">API keys</h2>
                    <ul className="divide-y rounded-lg border border-border text-sm">
                        {apiKeys.length === 0 ? (
                            <li className="px-3 py-2 text-muted-foreground">No API keys.</li>
                        ) : (
                            apiKeys.map((k) => (
                                <li key={k.id} className="px-3 py-2">
                                    <span className="font-mono text-xs">#{k.id}</span>{' '}
                                    <span className="text-muted-foreground">
                                        {k.environment} · {k.status}
                                    </span>
                                    {k.label ? (
                                        <span className="ml-2 text-muted-foreground">{k.label}</span>
                                    ) : null}
                                </li>
                            ))
                        )}
                    </ul>
                </section>

                <section className="space-y-2">
                    <h2 className="text-sm font-semibold">Activity</h2>
                    <ul className="max-h-96 divide-y overflow-auto rounded-lg border border-border text-xs">
                        {activity.length === 0 ? (
                            <li className="px-3 py-2 text-muted-foreground">No audit rows.</li>
                        ) : (
                            activity.map((a) => (
                                <li key={a.id} className="px-3 py-2">
                                    <span className="font-mono">#{a.id}</span> {a.action}{' '}
                                    <span className="text-muted-foreground">{a.stream}</span>
                                    {a.created_at ? (
                                        <span className="ml-2 text-muted-foreground">
                                            {new Date(a.created_at).toLocaleString()}
                                        </span>
                                    ) : null}
                                </li>
                            ))
                        )}
                    </ul>
                </section>
            </div>
        </AppLayout>
    );
}
