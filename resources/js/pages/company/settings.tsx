import { Form, Head, Link, useForm, usePage } from '@inertiajs/react';
import { Wallet } from '@phosphor-icons/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import CompanySettingsLayout from '@/layouts/company/layout';
import company from '@/routes/company';
import type { BreadcrumbItem, SharedCompany } from '@/types';

type SpendPolicySnapshot = {
    per_tx_limit_usd: string | number | null;
    daily_spend_limit_usd: string | number | null;
    daily_tx_count: number | null;
    require_approval_above: string | number | null;
    approval_timeout_secs: number | null;
    max_new_payees_per_day: number | null;
    business_hours_only: boolean;
    velocity_sensitivity: string;
};

type SpendPolicyWallet = {
    id: number;
    public_id: string;
    environment: string;
    policy: SpendPolicySnapshot | null;
};

function formatDate(iso: string): string {
    try {
        return new Date(iso).toLocaleString(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    } catch {
        return iso;
    }
}

export default function CompanySettings() {
    const page = usePage<{
        company: SharedCompany;
        canManageInvites: boolean;
        canManageWebhooks: boolean;
        canManageWallets: boolean;
        spendPolicyWallet: SpendPolicyWallet | null;
        canSubmitKybReview: boolean;
        canRequestLiveAccess: boolean;
        isCompanyOwner: boolean;
        kybStatus: string;
        liveEnabledAt: string | null;
        flash?: { status?: string | null };
    }>();
    const {
        company: companyContext,
        canManageInvites,
        canManageWebhooks,
        canManageWallets,
        spendPolicyWallet,
        canSubmitKybReview,
        canRequestLiveAccess,
        isCompanyOwner,
        kybStatus,
        liveEnabledAt,
        flash,
    } = page.props;
    const pageErrors = page.props.errors as Record<string, string> | undefined;

    const policyForm = useForm({
        per_tx_limit_usd:
            spendPolicyWallet?.policy?.per_tx_limit_usd != null &&
            spendPolicyWallet.policy.per_tx_limit_usd !== ''
                ? String(spendPolicyWallet.policy.per_tx_limit_usd)
                : '',
        velocity_sensitivity:
            spendPolicyWallet?.policy?.velocity_sensitivity ?? 'medium',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Company', href: company.settings() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Company settings" />

            <CompanySettingsLayout>
                <div className="space-y-8">
                    <Heading
                        variant="small"
                        title="General"
                        description={
                            companyContext?.name
                                ? `Organization: ${companyContext.name}`
                                : 'Manage your organization in Budera.'
                        }
                    />

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

                    {pageErrors?.live_access ? (
                        <p
                            className="rounded-lg border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                            role="alert"
                        >
                            {pageErrors.live_access}
                        </p>
                    ) : null}

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Live access (KYB)</CardTitle>
                            <CardDescription>
                                Complete KYB review, then request production access. Budera
                                enables live API keys after final approval.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Status:{' '}
                                <span className="font-mono text-foreground">{kybStatus}</span>
                            </p>
                            {liveEnabledAt ? (
                                <p className="text-sm text-muted-foreground">
                                    Live enabled: {formatDate(liveEnabledAt)}
                                </p>
                            ) : null}
                            {canSubmitKybReview ? (
                                <Button variant="default" asChild>
                                    <Link href={company.kyb.form.url()} prefetch>
                                        Start KYB application
                                    </Link>
                                </Button>
                            ) : null}
                            {canRequestLiveAccess ? (
                                <Form action={company.liveAccess.request.url()} method="post">
                                    <Button type="submit" variant="default">
                                        Request live access
                                    </Button>
                                </Form>
                            ) : null}
                            {isCompanyOwner &&
                            !canSubmitKybReview &&
                            !canRequestLiveAccess &&
                            !liveEnabledAt && (
                                <p className="text-sm text-muted-foreground">
                                    {kybStatus === 'pending' || kybStatus === 'under_review'
                                        ? 'KYB review is in progress.'
                                        : null}
                                    {kybStatus === 'rejected'
                                        ? 'KYB was rejected. Contact support or resubmit when eligible.'
                                        : null}
                                    {kybStatus === 'not_started'
                                        ? 'Submit for KYB review when you are ready for production.'
                                        : null}
                                </p>
                            )}
                            {!isCompanyOwner ? (
                                <p className="text-sm text-muted-foreground">
                                    Only the company owner can submit for KYB review or request
                                    live access.
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>

                    {canManageInvites ? (
                        <p className="text-sm text-muted-foreground">
                            <Link
                                href={company.oauthApps.index.url()}
                                className="font-medium text-primary underline-offset-4 hover:underline"
                            >
                                OAuth applications
                            </Link>{' '}
                            &mdash; register API clients for authorization code + PKCE.
                        </p>
                    ) : null}

                    {canManageWebhooks ? (
                        <p className="text-sm text-muted-foreground">
                            <Link
                                href={company.webhooks.index.url()}
                                className="font-medium text-primary underline-offset-4 hover:underline"
                            >
                                Webhook endpoints
                            </Link>{' '}
                            &mdash; subscribe to Budera events with HMAC-signed deliveries.
                        </p>
                    ) : null}

                    {canManageWallets && spendPolicyWallet ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Wallet
                                        className="size-5 text-muted-foreground"
                                        weight="duotone"
                                    />
                                    Spend policy
                                </CardTitle>
                                <CardDescription>
                                    Controls for wallet{' '}
                                    <span className="font-mono text-foreground">
                                        {spendPolicyWallet.public_id}
                                    </span>{' '}
                                    ({spendPolicyWallet.environment}).
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    className="space-y-4 sm:max-w-md"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        policyForm.patch(
                                            company.wallets.policy.update.url({
                                                walletAccount: spendPolicyWallet.public_id,
                                            }),
                                            { preserveScroll: true },
                                        );
                                    }}
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="policy-per-tx">
                                            Per-transaction limit (USD)
                                        </Label>
                                        <Input
                                            id="policy-per-tx"
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            value={policyForm.data.per_tx_limit_usd}
                                            onChange={(e) =>
                                                policyForm.setData('per_tx_limit_usd', e.target.value)
                                            }
                                            placeholder="No limit"
                                        />
                                        <InputError message={policyForm.errors.per_tx_limit_usd} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="policy-velocity">
                                            Velocity sensitivity
                                        </Label>
                                        <select
                                            id="policy-velocity"
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
                                            value={policyForm.data.velocity_sensitivity}
                                            onChange={(e) =>
                                                policyForm.setData('velocity_sensitivity', e.target.value)
                                            }
                                        >
                                            <option value="low">Low</option>
                                            <option value="medium">Medium</option>
                                            <option value="high">High</option>
                                        </select>
                                        <InputError message={policyForm.errors.velocity_sensitivity} />
                                    </div>
                                    <Button type="submit" disabled={policyForm.processing}>
                                        {policyForm.processing && <Spinner className="mr-2 size-4" />}
                                        Save spend policy
                                    </Button>
                                    <p className="text-xs text-muted-foreground">
                                        <Link
                                            className="text-primary underline-offset-4 hover:underline"
                                            href={company.wallets.policy.show.url(spendPolicyWallet.public_id)}
                                            prefetch
                                        >
                                            Full policy editor
                                        </Link>
                                        <span className="mx-1">&middot;</span>
                                        <Link
                                            className="text-primary underline-offset-4 hover:underline"
                                            href={company.wallets.index.url()}
                                            prefetch
                                        >
                                            All wallets
                                        </Link>
                                    </p>
                                </form>
                            </CardContent>
                        </Card>
                    ) : null}
                </div>
            </CompanySettingsLayout>
        </AppLayout>
    );
}
