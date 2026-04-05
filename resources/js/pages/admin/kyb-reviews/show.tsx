import { Form, Head, Link, useForm, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

type ReviewDetail = {
    id: number;
    company_id: number;
    company_name: string | null;
    owner_email: string | null;
    environment: string;
    status: string;
    decided_at: string | null;
    notes: string | null;
    documents: unknown;
    questionnaire: Record<string, unknown> | null;
};

export default function AdminKybReviewsShow() {
    const page = usePage<{
        review: ReviewDetail;
        flash?: { status?: string | null };
    }>();
    const { review, flash } = page.props;
    const pageErrors = page.props.errors as Record<string, string> | undefined;

    const rejectForm = useForm({
        reason: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'KYB reviews', href: admin.kybReviews.index.url() },
        {
            title: `Review #${review.id}`,
            href: admin.kybReviews.show.url({ kybReview: review.id }),
        },
    ];

    const documentsJson =
        review.documents === null || review.documents === undefined
            ? '—'
            : JSON.stringify(review.documents, null, 2);

    const questionnaireEntries =
        review.questionnaire && typeof review.questionnaire === 'object'
            ? Object.entries(review.questionnaire)
            : [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`KYB review #${review.id}`} />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title={review.company_name ?? `Company #${review.company_id}`}
                        description={`Environment: ${review.environment} · Status: ${review.status}`}
                    />
                    <Button variant="outline" size="sm" asChild>
                        <Link href={admin.kybReviews.index.url()}>Back to list</Link>
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

                {pageErrors?.kyb ? (
                    <p
                        className="rounded-lg border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                        role="alert"
                    >
                        {pageErrors.kyb}
                    </p>
                ) : null}

                <div className="space-y-2 rounded-lg border border-border bg-card p-4">
                    <p className="text-sm font-medium text-foreground">Owner</p>
                    <p className="text-sm text-muted-foreground">
                        {review.owner_email ?? '—'}
                    </p>
                </div>

                {questionnaireEntries.length > 0 ? (
                    <div className="space-y-4">
                        <p className="text-sm font-semibold text-foreground">
                            KYB questionnaire (submitted by company)
                        </p>
                        {questionnaireEntries.map(([section, value]) => (
                            <div
                                key={section}
                                className="space-y-2 rounded-lg border border-border bg-card p-4"
                            >
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    {section.replace(/_/g, ' ')}
                                </p>
                                <pre className="max-h-80 overflow-auto whitespace-pre-wrap wrap-break-word rounded-md bg-muted/50 p-3 text-xs">
                                    {typeof value === 'object' && value !== null
                                        ? JSON.stringify(value, null, 2)
                                        : String(value)}
                                </pre>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="space-y-2 rounded-lg border border-border bg-card p-4">
                        <p className="text-sm font-medium text-foreground">KYB questionnaire</p>
                        <p className="text-sm text-muted-foreground">
                            No structured questionnaire on file (legacy submission).
                        </p>
                    </div>
                )}

                <div className="space-y-2 rounded-lg border border-border bg-card p-4">
                    <p className="text-sm font-medium text-foreground">Uploaded documents (metadata)</p>
                    <pre className="max-h-64 overflow-auto rounded-md bg-muted/50 p-3 text-xs">
                        {documentsJson}
                    </pre>
                </div>

                {review.status === 'pending' ? (
                    <Form
                        action={admin.kybReviews.startReview.url({
                            kybReview: review.id,
                        })}
                        method="post"
                        className="flex flex-wrap gap-2"
                    >
                        <Button type="submit" variant="default">
                            Start review
                        </Button>
                    </Form>
                ) : null}

                {review.status === 'under_review' ? (
                    <div className="space-y-6">
                        <Form
                            action={admin.kybReviews.approve.url({
                                kybReview: review.id,
                            })}
                            method="post"
                        >
                            <Button type="submit" variant="default">
                                Approve (enable live)
                            </Button>
                        </Form>

                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                rejectForm.post(
                                    admin.kybReviews.reject.url({
                                        kybReview: review.id,
                                    }),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => rejectForm.reset(),
                                    },
                                );
                            }}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="reject-reason">Reject with reason</Label>
                                <Input
                                    id="reject-reason"
                                    value={rejectForm.data.reason}
                                    onChange={(e) =>
                                        rejectForm.setData('reason', e.target.value)
                                    }
                                    placeholder="Explain what is missing or incorrect"
                                />
                                <InputError message={rejectForm.errors.reason} />
                            </div>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={rejectForm.processing}
                            >
                                Reject
                            </Button>
                        </form>
                    </div>
                ) : null}

                {review.notes ? (
                    <div className="rounded-lg border border-border bg-muted/30 p-4 text-sm">
                        <p className="font-medium">Notes</p>
                        <p className="mt-1 text-muted-foreground">{review.notes}</p>
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}
