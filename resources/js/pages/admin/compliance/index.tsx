import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

type FlagRow = {
    id: number;
    flag_type: string;
    severity: string;
    created_at: string | null;
    payment_public_id: string | null;
    wallet_public_id: string | null;
};

export default function AdminComplianceIndex() {
    const page = usePage<{ flags: FlagRow[] }>();
    const { flags } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Compliance', href: admin.compliance.index.url() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Compliance flags" />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <Heading
                    variant="small"
                    title="Unresolved compliance flags"
                    description="Flags raised by spend-control screening."
                />

                <div className="space-y-3">
                    {flags.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No open flags.</p>
                    ) : (
                        flags.map((row) => (
                            <div
                                key={row.id}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-4"
                            >
                                <div className="min-w-0 space-y-1">
                                    <p className="font-medium text-foreground">{row.flag_type}</p>
                                    <p className="text-sm text-muted-foreground">
                                        Severity:{' '}
                                        <span className="font-mono text-xs">{row.severity}</span>
                                        {row.payment_public_id ? (
                                            <>
                                                {' '}
                                                · Payment{' '}
                                                <span className="font-mono text-xs">
                                                    {row.payment_public_id}
                                                </span>
                                            </>
                                        ) : null}
                                    </p>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={admin.compliance.show.url(row.id)} prefetch>
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
