import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
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
import company from '@/routes/company';
import type { BreadcrumbItem } from '@/types';

type WalletHeader = {
    public_id: string;
    environment: string;
    status: string;
};

type PolicyPayload = {
    agent_type: string | null;
    per_tx_limit_usd: string | number | null;
    daily_spend_limit_usd: string | number | null;
    daily_tx_count: number | null;
    allowed_categories: string[] | null;
    blocked_payees: string[] | null;
    require_approval_above: string | number | null;
    approval_timeout_secs: number | null;
    max_new_payees_per_day: number | null;
    business_hours_only: boolean;
    velocity_sensitivity: string;
    auto_topup: Record<string, unknown> | null;
} | null;

function stringifyJson(value: unknown, fallback: string): string {
    if (value === null || value === undefined) {
        return fallback;
    }
    try {
        return JSON.stringify(value, null, 2);
    } catch {
        return fallback;
    }
}

function parseJsonField(raw: string, label: string): { ok: true; value: unknown } | { ok: false; error: string } {
    const t = raw.trim();
    if (t === '') {
        return { ok: true, value: null };
    }
    try {
        return { ok: true, value: JSON.parse(t) as unknown };
    } catch {
        return { ok: false, error: `${label} must be valid JSON.` };
    }
}

export default function CompanyWalletPolicy() {
    const page = usePage<{
        wallet: WalletHeader;
        canManagePolicy: boolean;
        policy: PolicyPayload;
        flash?: { status?: string | null };
    }>();

    const { wallet, canManagePolicy, policy, flash } = page.props;

    const [jsonErrors, setJsonErrors] = useState<Record<string, string>>({});

    const defaults = useMemo(
        () => ({
            agent_type: policy?.agent_type ?? '',
            per_tx_limit_usd:
                policy?.per_tx_limit_usd != null && policy.per_tx_limit_usd !== ''
                    ? String(policy.per_tx_limit_usd)
                    : '',
            daily_spend_limit_usd:
                policy?.daily_spend_limit_usd != null && policy.daily_spend_limit_usd !== ''
                    ? String(policy.daily_spend_limit_usd)
                    : '',
            daily_tx_count:
                policy?.daily_tx_count != null ? String(policy.daily_tx_count) : '',
            allowed_categories_json: stringifyJson(policy?.allowed_categories ?? [], '[]'),
            blocked_payees_json: stringifyJson(policy?.blocked_payees ?? [], '[]'),
            require_approval_above:
                policy?.require_approval_above != null && policy.require_approval_above !== ''
                    ? String(policy.require_approval_above)
                    : '',
            approval_timeout_secs:
                policy?.approval_timeout_secs != null
                    ? String(policy.approval_timeout_secs)
                    : '',
            max_new_payees_per_day:
                policy?.max_new_payees_per_day != null
                    ? String(policy.max_new_payees_per_day)
                    : '',
            business_hours_only: policy?.business_hours_only ?? false,
            velocity_sensitivity: policy?.velocity_sensitivity ?? 'medium',
            auto_topup_json: stringifyJson(policy?.auto_topup ?? {}, '{}'),
        }),
        [policy],
    );

    const policyForm = useForm(defaults);

    const preview = useMemo(() => {
        const allowed = parseJsonField(
            policyForm.data.allowed_categories_json,
            'allowed_categories',
        );
        const blocked = parseJsonField(
            policyForm.data.blocked_payees_json,
            'blocked_payees',
        );
        const autoTop = parseJsonField(policyForm.data.auto_topup_json, 'auto_topup');

        return {
            agent_type: policyForm.data.agent_type || null,
            per_tx_limit_usd: policyForm.data.per_tx_limit_usd || null,
            daily_spend_limit_usd: policyForm.data.daily_spend_limit_usd || null,
            daily_tx_count: policyForm.data.daily_tx_count || null,
            allowed_categories: allowed.ok ? allowed.value : null,
            blocked_payees: blocked.ok ? blocked.value : null,
            require_approval_above: policyForm.data.require_approval_above || null,
            approval_timeout_secs: policyForm.data.approval_timeout_secs || null,
            max_new_payees_per_day: policyForm.data.max_new_payees_per_day || null,
            business_hours_only: policyForm.data.business_hours_only,
            velocity_sensitivity: policyForm.data.velocity_sensitivity,
            auto_topup: autoTop.ok ? autoTop.value : null,
        };
    }, [policyForm.data]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Wallets', href: company.wallets.index.url() },
        {
            title: wallet.public_id,
            href: company.wallets.show.url(wallet.public_id),
        },
        { title: 'Policy', href: company.wallets.policy.show.url(wallet.public_id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Policy · ${wallet.public_id}`} />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Spend policy"
                        description={`${wallet.public_id} · ${wallet.environment}`}
                    />
                    <Button variant="outline" size="sm" asChild>
                        <Link href={company.wallets.show.url(wallet.public_id)} prefetch>
                            Back to wallet
                        </Link>
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

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="lg:col-span-1">
                        <CardHeader>
                            <CardTitle>Rules</CardTitle>
                            <CardDescription>
                                {canManagePolicy
                                    ? 'Edit spend controls for this wallet.'
                                    : 'You can view this policy; only members with manage permission can edit.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {canManagePolicy ? (
                                <form
                                    className="space-y-4"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        const next: Record<string, string> = {};
                                        const a = parseJsonField(
                                            policyForm.data.allowed_categories_json,
                                            'Allowed categories',
                                        );
                                        const b = parseJsonField(
                                            policyForm.data.blocked_payees_json,
                                            'Blocked payees',
                                        );
                                        const c = parseJsonField(
                                            policyForm.data.auto_topup_json,
                                            'Auto top-up',
                                        );
                                        if (!a.ok) {
                                            next.allowed_categories_json = a.error;
                                        }
                                        if (!b.ok) {
                                            next.blocked_payees_json = b.error;
                                        }
                                        if (!c.ok) {
                                            next.auto_topup_json = c.error;
                                        }
                                        setJsonErrors(next);
                                        if (Object.keys(next).length > 0) {
                                            return;
                                        }
                                        policyForm.transform((data) => ({
                                            agent_type: data.agent_type || null,
                                            per_tx_limit_usd: data.per_tx_limit_usd || null,
                                            daily_spend_limit_usd: data.daily_spend_limit_usd || null,
                                            daily_tx_count: data.daily_tx_count || null,
                                            allowed_categories: a.ok ? a.value : null,
                                            blocked_payees: b.ok ? b.value : null,
                                            require_approval_above: data.require_approval_above || null,
                                            approval_timeout_secs: data.approval_timeout_secs || null,
                                            max_new_payees_per_day: data.max_new_payees_per_day || null,
                                            business_hours_only: data.business_hours_only,
                                            velocity_sensitivity: data.velocity_sensitivity,
                                            auto_topup: c.ok ? c.value : null,
                                        }));
                                        policyForm.patch(
                                            company.wallets.policy.update.url(wallet.public_id),
                                            { preserveScroll: true },
                                        );
                                    }}
                                >
                                    <div className="space-y-2">
                                        <Label htmlFor="agent_type">Agent type</Label>
                                        <Input
                                            id="agent_type"
                                            value={policyForm.data.agent_type}
                                            onChange={(e) =>
                                                policyForm.setData('agent_type', e.target.value)
                                            }
                                        />
                                        <InputError message={policyForm.errors.agent_type} />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="per_tx">Per-tx limit (USD)</Label>
                                            <Input
                                                id="per_tx"
                                                inputMode="decimal"
                                                value={policyForm.data.per_tx_limit_usd}
                                                onChange={(e) =>
                                                    policyForm.setData(
                                                        'per_tx_limit_usd',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <InputError message={policyForm.errors.per_tx_limit_usd} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="daily_spend">Daily spend (USD)</Label>
                                            <Input
                                                id="daily_spend"
                                                inputMode="decimal"
                                                value={policyForm.data.daily_spend_limit_usd}
                                                onChange={(e) =>
                                                    policyForm.setData(
                                                        'daily_spend_limit_usd',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={policyForm.errors.daily_spend_limit_usd}
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="daily_tx">Daily tx count</Label>
                                        <Input
                                            id="daily_tx"
                                            inputMode="numeric"
                                            value={policyForm.data.daily_tx_count}
                                            onChange={(e) =>
                                                policyForm.setData('daily_tx_count', e.target.value)
                                            }
                                        />
                                        <InputError message={policyForm.errors.daily_tx_count} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="allowed_cat">Allowed categories (JSON)</Label>
                                        <textarea
                                            id="allowed_cat"
                                            className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-24 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                            value={policyForm.data.allowed_categories_json}
                                            onChange={(e) =>
                                                policyForm.setData(
                                                    'allowed_categories_json',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                jsonErrors.allowed_categories_json
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="blocked">Blocked payees (JSON)</Label>
                                        <textarea
                                            id="blocked"
                                            className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-24 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                            value={policyForm.data.blocked_payees_json}
                                            onChange={(e) =>
                                                policyForm.setData(
                                                    'blocked_payees_json',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={jsonErrors.blocked_payees_json}
                                        />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="approval_above">
                                                Require approval above (USD)
                                            </Label>
                                            <Input
                                                id="approval_above"
                                                inputMode="decimal"
                                                value={policyForm.data.require_approval_above}
                                                onChange={(e) =>
                                                    policyForm.setData(
                                                        'require_approval_above',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={policyForm.errors.require_approval_above}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="approval_timeout">
                                                Approval timeout (seconds)
                                            </Label>
                                            <Input
                                                id="approval_timeout"
                                                inputMode="numeric"
                                                value={policyForm.data.approval_timeout_secs}
                                                onChange={(e) =>
                                                    policyForm.setData(
                                                        'approval_timeout_secs',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={policyForm.errors.approval_timeout_secs}
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="max_payees">Max new payees / day</Label>
                                        <Input
                                            id="max_payees"
                                            inputMode="numeric"
                                            value={policyForm.data.max_new_payees_per_day}
                                            onChange={(e) =>
                                                policyForm.setData(
                                                    'max_new_payees_per_day',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={policyForm.errors.max_new_payees_per_day}
                                        />
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <input
                                            id="biz_hours"
                                            type="checkbox"
                                            checked={policyForm.data.business_hours_only}
                                            onChange={(e) =>
                                                policyForm.setData(
                                                    'business_hours_only',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        <Label htmlFor="biz_hours" className="font-normal">
                                            Business hours only
                                        </Label>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="velocity">Velocity sensitivity</Label>
                                        <select
                                            id="velocity"
                                            className="border-input bg-background h-9 w-full rounded-md border px-2 text-sm"
                                            value={policyForm.data.velocity_sensitivity}
                                            onChange={(e) =>
                                                policyForm.setData(
                                                    'velocity_sensitivity',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="low">low</option>
                                            <option value="medium">medium</option>
                                            <option value="high">high</option>
                                        </select>
                                        <InputError
                                            message={policyForm.errors.velocity_sensitivity}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="auto_topup">Auto top-up (JSON)</Label>
                                        <textarea
                                            id="auto_topup"
                                            className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-24 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                            value={policyForm.data.auto_topup_json}
                                            onChange={(e) =>
                                                policyForm.setData('auto_topup_json', e.target.value)
                                            }
                                        />
                                        <InputError message={jsonErrors.auto_topup_json} />
                                    </div>

                                    <Button type="submit" disabled={policyForm.processing}>
                                        {policyForm.processing && (
                                            <Spinner className="mr-2 size-4" />
                                        )}
                                        Save policy
                                    </Button>
                                </form>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Contact a company owner or developer with wallet manage permission
                                    to change these rules.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Effective rules</CardTitle>
                            <CardDescription>
                                {canManagePolicy
                                    ? 'Preview from the current form state'
                                    : 'Current policy snapshot'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <pre className="max-h-[70vh] overflow-auto rounded-md border bg-muted/40 p-3 text-xs">
                                {JSON.stringify(
                                    canManagePolicy ? preview : policy,
                                    null,
                                    2,
                                )}
                            </pre>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
