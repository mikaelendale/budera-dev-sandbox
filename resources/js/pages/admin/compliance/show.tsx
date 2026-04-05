import { Form, Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

type FlagDetail = {
    id: number;
    flag_type: string;
    severity: string;
    details: Record<string, unknown> | null;
    resolved_at: string | null;
    resolved_by: number | null;
    created_at: string | null;
    payment: {
        public_id: string;
        status: string;
        amount_usd: string | null;
        wallet_public_id: string | null;
    } | null;
};

export default function AdminComplianceShow() {
    const page = usePage<{
        flag: FlagDetail;
        flash?: { status?: string | null };
    }>();
    const { flag, flash } = page.props;
    const isOpen = flag.resolved_at === null;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Compliance', href: admin.compliance.index.url() },
        { title: `Flag #${flag.id}`, href: admin.compliance.show.url(flag.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Compliance flag #${flag.id}`} />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title={flag.flag_type}
                        description={`Severity: ${flag.severity}`}
                    />
                    <Button variant="outline" size="sm" asChild>
                        <Link href={admin.compliance.index.url()}>Back</Link>
                    </Button>
                </div>

                {flash?.status ? (
                    <p
                        className="rounded-lg border border-border bg-muted/50 px-3 py-2 text-sm"
                        role="status"
                    >
                        {flash.status}
                    </p>
                ) : null}

                {flag.payment ? (
                    <section className="space-y-2 text-sm">
                        <h2 className="font-semibold">Payment</h2>
                        <p className="font-mono text-xs">{flag.payment.public_id}</p>
                        <p className="text-muted-foreground">
                            {flag.payment.status}
                            {flag.payment.amount_usd != null
                                ? ` · $${flag.payment.amount_usd}`
                                : ''}
                        </p>
                        {flag.payment.wallet_public_id ? (
                            <p className="font-mono text-xs text-muted-foreground">
                                Wallet {flag.payment.wallet_public_id}
                            </p>
                        ) : null}
                    </section>
                ) : null}

                <section className="space-y-2">
                    <h2 className="text-sm font-semibold">Details</h2>
                    <pre className="max-h-64 overflow-auto rounded-lg border bg-muted/40 p-3 text-xs">
                        {JSON.stringify(flag.details ?? {}, null, 2)}
                    </pre>
                </section>

                {isOpen ? (
                    <Form
                        action={admin.compliance.resolve.url(flag.id)}
                        method="post"
                        onSubmit={(e) => {
                            if (!window.confirm('Mark this flag as resolved?')) {
                                e.preventDefault();
                            }
                        }}
                    >
                        <Button type="submit">Resolve flag</Button>
                    </Form>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Resolved{flag.resolved_at ? ` at ${new Date(flag.resolved_at).toLocaleString()}` : ''}.
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
