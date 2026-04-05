import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import bankPartner from '@/routes/bank-partner';
import type { BreadcrumbItem } from '@/types';

type Stats = {
    total_accounts: number;
    active_accounts: number;
    total_balance_cents: number;
    total_companies: number;
};

function formatUsdFromCents(cents: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Bank partner', href: bankPartner.dashboard.url() },
];

export default function BankPartnerDashboard() {
    const { stats } = usePage<{ stats: Stats }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bank partner — Overview" />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <Heading
                    variant="small"
                    title="Bank partner overview"
                    description="Aggregate statistics across all companies and wallets."
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total accounts</CardDescription>
                            <CardTitle className="text-2xl tabular-nums">
                                {stats.total_accounts.toLocaleString()}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Active accounts</CardDescription>
                            <CardTitle className="text-2xl tabular-nums">
                                {stats.active_accounts.toLocaleString()}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total balance</CardDescription>
                            <CardTitle className="text-2xl tabular-nums">
                                {formatUsdFromCents(stats.total_balance_cents)}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Companies</CardDescription>
                            <CardTitle className="text-2xl tabular-nums">
                                {stats.total_companies.toLocaleString()}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
