import { Form, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
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
import { approve, deny } from '@/routes/payment-approvals';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    token: string;
    approvalStatus: string;
    expiresAt: string | null;
    payment: {
        public_id: string;
        amount_usd: string | null;
        payee_ref: string | null;
        wallet_public_id: string | undefined;
    };
    canDecide: boolean;
    flash?: {
        status?: string | null;
        error?: string | null;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Payment approval', href: '#' },
];

export default function PaymentApprovalShow() {
    const { token, approvalStatus, expiresAt, payment, canDecide, flash } =
        usePage<PageProps>().props;

    const isPending = approvalStatus === 'pending';
    const flashStatus = flash?.status;
    const flashError = flash?.error;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment approval" />

            <div className="mx-auto max-w-lg space-y-6 p-4 md:p-6">
                <Heading
                    title="Payment approval"
                    description="Review this outbound payment held for human approval."
                />

                {flashStatus ? (
                    <p className="text-sm text-muted-foreground">{flashStatus}</p>
                ) : null}
                {flashError ? (
                    <p className="text-sm text-destructive">{flashError}</p>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Payment details</CardTitle>
                        <CardDescription>
                            Wallet {payment.wallet_public_id ?? '—'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>
                            <span className="text-muted-foreground">Payment</span>{' '}
                            <span className="font-mono">{payment.public_id}</span>
                        </p>
                        {payment.amount_usd ? (
                            <p>
                                <span className="text-muted-foreground">Amount (USD)</span>{' '}
                                {payment.amount_usd}
                            </p>
                        ) : null}
                        {payment.payee_ref ? (
                            <p>
                                <span className="text-muted-foreground">Payee</span>{' '}
                                {payment.payee_ref}
                            </p>
                        ) : null}
                        <p>
                            <span className="text-muted-foreground">Status</span>{' '}
                            {approvalStatus}
                        </p>
                        {expiresAt ? (
                            <p className="text-muted-foreground">
                                Link expires {new Date(expiresAt).toLocaleString()}
                            </p>
                        ) : null}
                    </CardContent>
                </Card>

                {isPending && canDecide ? (
                    <div className="flex flex-wrap gap-3">
                        <Form action={approve.url({ token })} method="post">
                            <Button type="submit" variant="default">
                                Approve payment
                            </Button>
                        </Form>
                        <Form action={deny.url({ token })} method="post">
                            <Button type="submit" variant="outline">
                                Deny payment
                            </Button>
                        </Form>
                    </div>
                ) : null}

                {isPending && !canDecide ? (
                    <p className="text-sm text-muted-foreground">
                        You can view this request, but only members with wallet
                        management permission can approve or deny.
                    </p>
                ) : null}

                {!isPending ? (
                    <p className="text-sm text-muted-foreground">
                        No further action is available for this approval link.
                    </p>
                ) : null}
            </div>
        </AppLayout>
    );
}
