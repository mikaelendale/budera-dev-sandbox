import { Head, router, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import bankPartner from '@/routes/bank-partner';
import type { BreadcrumbItem } from '@/types';

type ReviewRow = {
    id: number;
    company_name: string | null;
    status: string;
    created_at: string | null;
    updated_at: string | null;
};

type PaginatedReviews = {
    data: ReviewRow[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status.includes('approved')) return 'default';
    if (status.includes('rejected')) return 'destructive';
    if (status.includes('under_review') || status.includes('pending'))
        return 'secondary';
    return 'outline';
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Bank partner', href: bankPartner.dashboard.url() },
    { title: 'KYB documents', href: bankPartner.kybDocuments.index.url() },
];

export default function BankPartnerKybDocuments() {
    const { reviews } = usePage<{ reviews: PaginatedReviews }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bank partner — KYB documents" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-6 md:px-6">
                <Heading
                    variant="small"
                    title="KYB documents"
                    description="Company onboarding reviews across all tenants."
                />

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/50 text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2">ID</th>
                                <th className="px-3 py-2">Company</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Submitted</th>
                                <th className="px-3 py-2">Updated</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {reviews.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No KYB reviews found.
                                    </td>
                                </tr>
                            ) : (
                                reviews.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {row.id}
                                        </td>
                                        <td className="px-3 py-2 font-medium">
                                            {row.company_name ?? '—'}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge variant={statusVariant(row.status)}>
                                                {row.status}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {row.created_at
                                                ? new Date(row.created_at).toLocaleDateString()
                                                : '—'}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {row.updated_at
                                                ? new Date(row.updated_at).toLocaleDateString()
                                                : '—'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {reviews.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Page {reviews.current_page} of {reviews.last_page}
                        </span>
                        <div className="flex gap-2">
                            {reviews.prev_page_url && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(reviews.prev_page_url!, {}, { preserveState: true })
                                    }
                                >
                                    Previous
                                </Button>
                            )}
                            {reviews.next_page_url && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(reviews.next_page_url!, {}, { preserveState: true })
                                    }
                                >
                                    Next
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
