import { Form, Head, useForm, usePage } from '@inertiajs/react';
import { Key, Repeat, Trash } from '@phosphor-icons/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import CompanySettingsLayout from '@/layouts/company/layout';
import company from '@/routes/company';
import type { BreadcrumbItem } from '@/types';

type ApiKeyRow = {
    id: number;
    environment: 'sandbox' | 'live';
    status: string;
    abilities: string[];
    key_preview: string;
    created_at: string | null;
    revoked_at: string | null;
};

export default function CompanyApiKeys() {
    const page = usePage<{
        apiKeys: ApiKeyRow[];
        oneTimePlainTextKey?: string | null;
        defaultEnvironment: 'sandbox' | 'live';
        defaultAbilities: string[];
        flash?: { status?: string | null };
    }>();

    const { apiKeys, oneTimePlainTextKey, flash, defaultAbilities } = page.props;
    const pageErrors = page.props.errors as Record<string, string> | undefined;

    const form = useForm({
        environment: 'sandbox' as 'sandbox' | 'live',
        abilities: [...(defaultAbilities ?? ['wallet:read', 'wallet:pay'])],
    });

    const allAbilities = [
        'wallet:read',
        'wallet:pay',
        'wallet:link',
        'wallet:topup',
        'wallet:transfer',
        'sandbox:simulate',
    ];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'API Keys', href: company.apiKeys.index.url() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="API keys" />

            <CompanySettingsLayout>
            <div className="space-y-8">
                <Heading
                    variant="small"
                    title="API keys"
                    description="Create and manage developer API keys for sandbox and live environments."
                />

                {flash?.status ? (
                    <p className="rounded-lg border border-border bg-muted/50 px-3 py-2 text-sm" role="status">
                        {flash.status}
                    </p>
                ) : null}

                {pageErrors?.rotate ? (
                    <p className="rounded-lg border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert">
                        {pageErrors.rotate}
                    </p>
                ) : null}

                {oneTimePlainTextKey ? (
                    <div className="rounded-lg border border-border bg-muted/50 p-4">
                        <p className="text-sm font-medium">Copy this key now (shown once):</p>
                        <code className="mt-2 block break-all rounded-md border border-border bg-background px-3 py-2 text-xs">
                            {oneTimePlainTextKey}
                        </code>
                    </div>
                ) : null}

                <section className="space-y-4 rounded-xl border border-border p-6">
                    <h2 className="flex items-center gap-2 text-base font-semibold">
                        <Key className="size-5 text-muted-foreground" />
                        New API key
                    </h2>

                    <form
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/company/api-keys', {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="api-key-environment">Environment</Label>
                            <Select
                                value={form.data.environment}
                                onValueChange={(value) =>
                                    form.setData(
                                        'environment',
                                        value as 'sandbox' | 'live',
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="api-key-environment"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select environment" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="sandbox">Sandbox</SelectItem>
                                    <SelectItem value="live">Live</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.environment} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Abilities</Label>
                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                {allAbilities.map((ability) => {
                                    const checked = form.data.abilities.includes(ability);

                                    return (
                                        <label
                                            key={ability}
                                            className="flex items-center gap-2 rounded-md border border-border p-2 text-sm"
                                        >
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={(next) => {
                                                    if (next === true) {
                                                        form.setData('abilities', [
                                                            ...new Set([
                                                                ...form.data.abilities,
                                                                ability,
                                                            ]),
                                                        ]);

                                                        return;
                                                    }

                                                    form.setData(
                                                        'abilities',
                                                        form.data.abilities.filter(
                                                            (item) => item !== ability,
                                                        ),
                                                    );
                                                }}
                                            />
                                            <span>{ability}</span>
                                        </label>
                                    );
                                })}
                            </div>
                            <InputError message={form.errors.abilities} />
                        </div>

                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner className="mr-2 size-4" />}
                            Create key
                        </Button>
                    </form>
                </section>

                <section className="space-y-3">
                    <h2 className="text-base font-semibold">Existing keys</h2>
                    {apiKeys.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No API keys yet.</p>
                    ) : (
                        <div className="divide-y divide-border rounded-lg border border-border">
                            {apiKeys.map((apiKey) => (
                                <div
                                    key={apiKey.id}
                                    className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="space-y-1 text-sm">
                                        <p className="font-medium">
                                            {apiKey.environment.toUpperCase()} · {apiKey.status}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {apiKey.key_preview}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Abilities: {apiKey.abilities.join(', ') || '—'}
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Form
                                            action={`/company/api-keys/${apiKey.id}/rotate`}
                                            method="post"
                                        >
                                            <Button type="submit" variant="outline" size="sm">
                                                <Repeat className="mr-1.5 size-4" />
                                                Rotate
                                            </Button>
                                        </Form>
                                        <Form
                                            action={`/company/api-keys/${apiKey.id}`}
                                            method="delete"
                                        >
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                className="text-destructive"
                                            >
                                                <Trash className="mr-1.5 size-4" />
                                                Revoke
                                            </Button>
                                        </Form>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
            </CompanySettingsLayout>
        </AppLayout>
    );
}
