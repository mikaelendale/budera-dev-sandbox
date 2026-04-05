import { Form, Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

type CompanyRow = {
    id: number;
    name: string;
    email: string | null;
    owner_email: string | null;
    kyb_status: string;
    updated_at: string | null;
};

export default function AdminLiveAccessIndex() {
    const page = usePage<{
        companies: CompanyRow[];
        flash?: { status?: string | null };
    }>();
    const { companies, flash } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Live access', href: admin.liveAccess.index.url() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Live access queue" />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Live access queue"
                        description="Companies approved for KYB awaiting production enablement."
                    />
                    <Button variant="outline" size="sm" asChild>
                        <Link href={admin.kybReviews.index.url()}>KYB reviews</Link>
                    </Button>
                </div>

                {flash?.status ? (
                    <p
                        className="rounded-lg border border-border bg-muted/50 px-3 py-2 text-sm text-foreground"
                        role="status"
                    >
                        {flash.status}
                    </p>
                ) : null}

                <div className="space-y-3">
                    {companies.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No companies are waiting for live access.
                        </p>
                    ) : (
                        companies.map((row) => (
                            <div
                                key={row.id}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-4"
                            >
                                <div className="min-w-0 space-y-1">
                                    <p className="font-medium text-foreground">{row.name}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {row.owner_email ?? row.email ?? '—'} ·{' '}
                                        <span className="font-mono text-xs">{row.kyb_status}</span>
                                    </p>
                                </div>
                                <Form
                                    action={admin.liveAccess.approve.url(row.id)}
                                    method="post"
                                >
                                    <Button type="submit" size="sm">
                                        Enable live
                                    </Button>
                                </Form>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
