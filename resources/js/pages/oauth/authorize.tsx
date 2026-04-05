import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type ScopeRow = { id: string; description: string };

type SummaryRow = { id: string; label: string };

type PolicyPreview = {
    per_tx_limit_usd: string | null;
    daily_spend_limit_usd: string | null;
    daily_tx_count: number | null;
    allowed_categories: string[];
    require_approval_above: string | null;
    blocked_payees_count: number;
    business_hours_only: boolean;
};

type Props = {
    client: { id: string; name: string };
    company?: { name: string; logo_url: string | null } | null;
    agentName?: string | null;
    walletPreview?: { public_id: string; environment: string } | null;
    policyPreview?: PolicyPreview | null;
    allowingSummaries?: SummaryRow[];
    denyingSummaries?: SummaryRow[];
    scopes: ScopeRow[];
    authToken: string;
    csrfToken: string;
    approveAction: string;
    denyAction: string;
};

export default function OAuthAuthorize({
    client,
    company,
    agentName,
    walletPreview,
    policyPreview,
    allowingSummaries = [],
    denyingSummaries = [],
    scopes,
    authToken,
    csrfToken,
    approveAction,
    denyAction,
}: Props) {
    const displayCompanyName = company?.name ?? client.name;

    return (
        <>
            <Head title="Authorize application" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-muted/30 p-4">
                <Card className="w-full max-w-2xl">
                    <CardHeader>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                            {company?.logo_url ? (
                                <img
                                    src={company.logo_url}
                                    alt=""
                                    className="size-12 shrink-0 rounded-lg border border-border object-cover"
                                />
                            ) : null}
                            <div className="min-w-0 flex-1 space-y-1">
                                <CardTitle className="text-xl">
                                    Connect to Budera
                                </CardTitle>
                                <CardDescription className="text-base text-foreground">
                                    <span className="font-semibold">
                                        {displayCompanyName}
                                    </span>{' '}
                                    is requesting access through{' '}
                                    <span className="font-medium">
                                        {client.name}
                                    </span>
                                    .
                                </CardDescription>
                                {agentName ? (
                                    <p className="text-sm text-muted-foreground">
                                        Agent or integration:{' '}
                                        <span className="font-medium text-foreground">
                                            {agentName}
                                        </span>
                                    </p>
                                ) : null}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {walletPreview ? (
                            <div className="rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm">
                                <p className="font-medium text-foreground">
                                    Wallet in scope
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    {walletPreview.public_id} ({walletPreview.environment})
                                </p>
                            </div>
                        ) : null}

                        {policyPreview ? (
                            <div className="rounded-lg border border-border px-4 py-3 text-sm">
                                <p className="font-medium text-foreground">
                                    Spend controls preview
                                </p>
                                <ul className="mt-2 list-inside list-disc space-y-1 text-muted-foreground">
                                    {policyPreview.per_tx_limit_usd ? (
                                        <li>
                                            Per-payment limit: $
                                            {policyPreview.per_tx_limit_usd} USD
                                        </li>
                                    ) : null}
                                    {policyPreview.daily_spend_limit_usd ? (
                                        <li>
                                            Daily spend cap: $
                                            {policyPreview.daily_spend_limit_usd} USD
                                        </li>
                                    ) : null}
                                    {policyPreview.daily_tx_count !== null ? (
                                        <li>
                                            Daily payment count cap:{' '}
                                            {policyPreview.daily_tx_count}
                                        </li>
                                    ) : null}
                                    {policyPreview.allowed_categories.length >
                                    0 ? (
                                        <li>
                                            Allowed categories:{' '}
                                            {policyPreview.allowed_categories.join(
                                                ', ',
                                            )}
                                        </li>
                                    ) : null}
                                    {policyPreview.require_approval_above ? (
                                        <li>
                                            Approvals required above $
                                            {
                                                policyPreview.require_approval_above
                                            }{' '}
                                            USD
                                        </li>
                                    ) : null}
                                    {policyPreview.blocked_payees_count > 0 ? (
                                        <li>
                                            Blocked payee patterns:{' '}
                                            {policyPreview.blocked_payees_count}
                                        </li>
                                    ) : null}
                                    {policyPreview.business_hours_only ? (
                                        <li>Business hours only</li>
                                    ) : null}
                                </ul>
                            </div>
                        ) : null}

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-4 py-3">
                                <p className="text-sm font-semibold text-foreground">
                                    What you&apos;re allowing
                                </p>
                                {allowingSummaries.length > 0 ? (
                                    <ul className="mt-2 list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                        {allowingSummaries.map((row) => (
                                            <li key={row.id}>{row.label}</li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Minimal access (no sensitive wallet
                                        scopes requested).
                                    </p>
                                )}
                            </div>
                            <div className="rounded-lg border border-border px-4 py-3">
                                <p className="text-sm font-semibold text-foreground">
                                    What you&apos;re NOT allowing
                                </p>
                                {denyingSummaries.length > 0 ? (
                                    <ul className="mt-2 list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                        {denyingSummaries.map((row) => (
                                            <li key={row.id}>{row.label}</li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        You are not withholding additional
                                        high-impact actions beyond what is shown
                                        under &quot;What you&apos;re
                                        allowing&quot; (or none apply to this
                                        integration).
                                    </p>
                                )}
                            </div>
                        </div>

                        <div>
                            <p className="text-sm font-medium text-foreground">
                                Requested scopes (technical)
                            </p>
                            <ul className="mt-2 list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                {scopes.map((s) => (
                                    <li key={s.id}>
                                        <span className="font-mono text-xs">
                                            {s.id}
                                        </span>
                                        {s.description ? (
                                            <span>
                                                {' '}
                                                — {s.description}
                                            </span>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </CardContent>
                    <CardFooter className="flex flex-wrap gap-2 border-t pt-6">
                        <form
                            action={approveAction}
                            method="post"
                            className="inline"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                value={csrfToken}
                            />
                            <input
                                type="hidden"
                                name="auth_token"
                                value={authToken}
                            />
                            <Button type="submit">Authorize</Button>
                        </form>
                        <form
                            action={denyAction}
                            method="post"
                            className="inline"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                value={csrfToken}
                            />
                            <input
                                type="hidden"
                                name="_method"
                                value="DELETE"
                            />
                            <input
                                type="hidden"
                                name="auth_token"
                                value={authToken}
                            />
                            <Button type="submit" variant="outline">
                                Deny
                            </Button>
                        </form>
                    </CardFooter>
                </Card>
            </div>
        </>
    );
}
