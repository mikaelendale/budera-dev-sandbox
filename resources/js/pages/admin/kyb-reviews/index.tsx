import { Head, Link, usePage } from '@inertiajs/react';
import { FileText } from '@phosphor-icons/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

type ReviewRow = {
    id: number;
    company_id: number;
    company_name: string | null;
    owner_email: string | null;
    environment: string;
    status: string;
    decided_at: string | null;
    updated_at: string | null;
};

export default function AdminKybReviewsIndex() {
    const page = usePage<{
        reviews: ReviewRow[];
        flash?: { status?: string | null };
    }>();
    const { reviews, flash } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'KYB reviews', href: admin.kybReviews.index.url() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="KYB reviews" />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="KYB reviews"
                        description="Company onboarding to live access."
                    />
                    <Button variant="outline" size="sm" asChild>
                        <Link href={admin.partnerBanks.index.url()}>
                            Partner banks
                        </Link>
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
                    {reviews.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No KYB reviews yet.
                        </p>
                    ) : (
                        reviews.map((row) => (
                            <div
                                key={row.id}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-4"
                            >
                                <div className="min-w-0 space-y-1">
                                    <p className="font-medium text-foreground">
                                        {row.company_name ?? `Company #${row.company_id}`}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {row.owner_email ?? '—'} · {row.environment}{' '}
                                        ·{' '}
                                        <span className="font-mono text-xs">
                                            {row.status}
                                        </span>
                                    </p>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={admin.kybReviews.show.url({
                                            kybReview: row.id,
                                        })}
                                    >
                                        <FileText className="mr-1.5 size-4" />
                                        Open
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
