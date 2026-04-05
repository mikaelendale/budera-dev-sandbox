import { Form, Head, Link, useForm, usePage } from '@inertiajs/react';
import { Bank, PencilSimple, Trash } from '@phosphor-icons/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

type IntegrationRow = {
    id: number;
    provider: string;
    label: string;
    environment: string;
    base_url: string | null;
    is_active: boolean;
    has_outbound_secret: boolean;
    has_inbound_webhook_secret: boolean;
    outbound_secret_preview: string | null;
    inbound_webhook_secret_preview: string | null;
    updated_at: string | null;
};

export default function AdminPartnerBanks() {
    const page = usePage<{
        integrations: IntegrationRow[];
        flash?: { status?: string | null; error?: string | null };
    }>();
    const { integrations, flash } = page.props;

    const [editingId, setEditingId] = useState<number | null>(null);

    const createForm = useForm({
        label: '',
        provider: '',
        environment: 'sandbox' as 'sandbox' | 'live',
        base_url: '',
        outbound_api_secret: '',
        inbound_webhook_secret: '',
    });

    const editForm = useForm({
        label: '',
        environment: 'sandbox' as 'sandbox' | 'live',
        base_url: '',
        outbound_api_secret: '',
        inbound_webhook_secret: '',
        is_active: true,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Partner banks', href: admin.partnerBanks.index.url() },
    ];

    function startEdit(row: IntegrationRow) {
        editForm.setData({
            label: row.label,
            environment: row.environment === 'live' ? 'live' : 'sandbox',
            base_url: row.base_url ?? '',
            outbound_api_secret: '',
            inbound_webhook_secret: '',
            is_active: row.is_active,
        });
        setEditingId(row.id);
    }

    function cancelEdit() {
        setEditingId(null);
        editForm.reset();
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Partner bank integrations" />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Partner bank integrations"
                        description="Budera-internal configuration for partner APIs (Column, mock bank, etc.). Secrets are encrypted at rest; only masked previews are shown after save."
                    />
                    <Button variant="outline" size="sm" asChild>
                        <Link href={admin.kybReviews.index.url()}>
                            KYB reviews
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
                {flash?.error ? (
                    <p
                        className="rounded-lg border border-destructive bg-destructive/10 px-3 py-2 text-sm text-destructive"
                        role="alert"
                    >
                        {flash.error}
                    </p>
                ) : null}

                <section className="space-y-4 rounded-xl border border-border p-6">
                    <h2 className="flex items-center gap-2 text-base font-semibold">
                        <Bank className="size-5 text-muted-foreground" />
                        New integration
                    </h2>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            createForm.post(admin.partnerBanks.store.url(), {
                                preserveScroll: true,
                                onSuccess: () =>
                                    createForm.reset(
                                        'label',
                                        'provider',
                                        'environment',
                                        'base_url',
                                        'outbound_api_secret',
                                        'inbound_webhook_secret',
                                    ),
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="pbi-label">Label</Label>
                            <Input
                                id="pbi-label"
                                value={createForm.data.label}
                                onChange={(e) =>
                                    createForm.setData('label', e.target.value)
                                }
                                placeholder="e.g. Mock bank sandbox"
                                required
                            />
                            <InputError message={createForm.errors.label} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="pbi-provider">Provider key</Label>
                            <Input
                                id="pbi-provider"
                                value={createForm.data.provider}
                                onChange={(e) =>
                                    createForm.setData(
                                        'provider',
                                        e.target.value,
                                    )
                                }
                                placeholder="e.g. mock_bank or column"
                                required
                            />
                            <p className="text-xs text-muted-foreground">
                                Used by resolvers and `ColumnBankService` binding
                                (not shown to AI companies).
                            </p>
                            <InputError message={createForm.errors.provider} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Environment</Label>
                            <Select
                                value={createForm.data.environment}
                                onValueChange={(v) =>
                                    createForm.setData(
                                        'environment',
                                        v as 'sandbox' | 'live',
                                    )
                                }
                            >
                                <SelectTrigger className="w-full sm:max-w-xs">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="sandbox">
                                        Sandbox
                                    </SelectItem>
                                    <SelectItem value="live">Live</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                message={createForm.errors.environment}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="pbi-base-url">
                                Base URL (optional)
                            </Label>
                            <Input
                                id="pbi-base-url"
                                type="url"
                                value={createForm.data.base_url}
                                onChange={(e) =>
                                    createForm.setData(
                                        'base_url',
                                        e.target.value,
                                    )
                                }
                                placeholder="https://…"
                            />
                            <InputError message={createForm.errors.base_url} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="pbi-out">
                                Outbound API secret (optional)
                            </Label>
                            <Input
                                id="pbi-out"
                                type="password"
                                autoComplete="off"
                                value={createForm.data.outbound_api_secret}
                                onChange={(e) =>
                                    createForm.setData(
                                        'outbound_api_secret',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={createForm.errors.outbound_api_secret}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="pbi-in">
                                Inbound webhook secret (optional)
                            </Label>
                            <Input
                                id="pbi-in"
                                type="password"
                                autoComplete="off"
                                value={createForm.data.inbound_webhook_secret}
                                onChange={(e) =>
                                    createForm.setData(
                                        'inbound_webhook_secret',
                                        e.target.value,
                                    )
                                }
                                placeholder="whsec_…"
                            />
                            <InputError
                                message={
                                    createForm.errors.inbound_webhook_secret
                                }
                            />
                        </div>
                        <Button type="submit" disabled={createForm.processing}>
                            {createForm.processing && (
                                <Spinner className="mr-2 size-4" />
                            )}
                            Save integration
                        </Button>
                    </form>
                </section>

                <section className="space-y-3">
                    <h2 className="text-base font-semibold">Integrations</h2>
                    {integrations.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No partner bank integrations yet.
                        </p>
                    ) : (
                        <div className="divide-y divide-border rounded-lg border border-border">
                            {integrations.map((row) => (
                                <div
                                    key={row.id}
                                    className="flex flex-col gap-3 px-4 py-4"
                                >
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p className="font-medium">
                                                {row.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {row.provider} ·{' '}
                                                {row.environment}
                                                {row.is_active
                                                    ? ''
                                                    : ' · inactive'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {row.base_url ?? '—'}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Outbound:{' '}
                                                {row.has_outbound_secret
                                                    ? (row.outbound_secret_preview ??
                                                      'set')
                                                    : 'not set'}
                                                {' · '}Inbound:{' '}
                                                {row.has_inbound_webhook_secret
                                                    ? (row.inbound_webhook_secret_preview ??
                                                      'set')
                                                    : 'not set'}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 gap-2">
                                            <Form
                                                action={admin.partnerBanks.test.url(
                                                    {
                                                        integration: row.id,
                                                    },
                                                )}
                                                method="post"
                                            >
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    Test
                                                </Button>
                                            </Form>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    editingId === row.id
                                                        ? cancelEdit()
                                                        : startEdit(row)
                                                }
                                            >
                                                <PencilSimple className="mr-1.5 size-4" />
                                                {editingId === row.id
                                                    ? 'Cancel'
                                                    : 'Edit'}
                                            </Button>
                                            <Form
                                                action={admin.partnerBanks.destroy.url(
                                                    {
                                                        integration: row.id,
                                                    },
                                                )}
                                                method="delete"
                                                className="shrink-0"
                                            >
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    size="sm"
                                                    className="text-destructive"
                                                >
                                                    <Trash className="mr-1.5 size-4" />
                                                    Remove
                                                </Button>
                                            </Form>
                                        </div>
                                    </div>

                                    {editingId === row.id ? (
                                        <form
                                            className="space-y-4 rounded-lg border border-border bg-muted/30 p-4"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                editForm.patch(
                                                    admin.partnerBanks.update.url(
                                                        {
                                                            integration:
                                                                row.id,
                                                        },
                                                    ),
                                                    {
                                                        preserveScroll: true,
                                                        onSuccess: () =>
                                                            cancelEdit(),
                                                    },
                                                );
                                            }}
                                        >
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`edit-label-${row.id}`}
                                                >
                                                    Label
                                                </Label>
                                                <Input
                                                    id={`edit-label-${row.id}`}
                                                    value={editForm.data.label}
                                                    onChange={(e) =>
                                                        editForm.setData(
                                                            'label',
                                                            e.target.value,
                                                        )
                                                    }
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        editForm.errors.label
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label>Environment</Label>
                                                <Select
                                                    value={
                                                        editForm.data
                                                            .environment
                                                    }
                                                    onValueChange={(v) =>
                                                        editForm.setData(
                                                            'environment',
                                                            v as
                                                                | 'sandbox'
                                                                | 'live',
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="w-full sm:max-w-xs">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="sandbox">
                                                            Sandbox
                                                        </SelectItem>
                                                        <SelectItem value="live">
                                                            Live
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`edit-url-${row.id}`}
                                                >
                                                    Base URL
                                                </Label>
                                                <Input
                                                    id={`edit-url-${row.id}`}
                                                    type="url"
                                                    value={
                                                        editForm.data.base_url
                                                    }
                                                    onChange={(e) =>
                                                        editForm.setData(
                                                            'base_url',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`edit-out-${row.id}`}
                                                >
                                                    Outbound API secret (leave
                                                    blank to keep current)
                                                </Label>
                                                <Input
                                                    id={`edit-out-${row.id}`}
                                                    type="password"
                                                    autoComplete="off"
                                                    value={
                                                        editForm.data
                                                            .outbound_api_secret
                                                    }
                                                    onChange={(e) =>
                                                        editForm.setData(
                                                            'outbound_api_secret',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        editForm.errors
                                                            .outbound_api_secret
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`edit-in-${row.id}`}
                                                >
                                                    Inbound webhook secret
                                                    (leave blank to keep
                                                    current)
                                                </Label>
                                                <Input
                                                    id={`edit-in-${row.id}`}
                                                    type="password"
                                                    autoComplete="off"
                                                    value={
                                                        editForm.data
                                                            .inbound_webhook_secret
                                                    }
                                                    onChange={(e) =>
                                                        editForm.setData(
                                                            'inbound_webhook_secret',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        editForm.errors
                                                            .inbound_webhook_secret
                                                    }
                                                />
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    id={`edit-active-${row.id}`}
                                                    checked={
                                                        editForm.data.is_active
                                                    }
                                                    onCheckedChange={(v) =>
                                                        editForm.setData(
                                                            'is_active',
                                                            v === true,
                                                        )
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`edit-active-${row.id}`}
                                                    className="font-normal"
                                                >
                                                    Active
                                                </Label>
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={editForm.processing}
                                            >
                                                {editForm.processing && (
                                                    <Spinner className="mr-2 size-4" />
                                                )}
                                                Save changes
                                            </Button>
                                        </form>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
