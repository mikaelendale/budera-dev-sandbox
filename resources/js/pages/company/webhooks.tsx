import { Form, Head, useForm, usePage } from '@inertiajs/react';
import { Lightning, Trash } from '@phosphor-icons/react';
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
import CompanySettingsLayout from '@/layouts/company/layout';
import company from '@/routes/company';
import type { BreadcrumbItem } from '@/types';
import { useMemo } from 'react';

type WebhookEndpointRow = {
    id: number;
    url: string;
    events: string[];
    environment: 'sandbox' | 'live';
    is_active: boolean;
    created_at: string | null;
};

type DeliveryRow = {
    id: number;
    event: string;
    status: string;
    attempts: number;
    response_status: number | null;
    last_attempted_at: string | null;
    endpoint_id: number;
};

function toggleEvent(events: string[], name: string, checked: boolean): string[] {
    if (name === '*') {
        return checked ? ['*'] : [];
    }

    const withoutStar = events.filter((e) => e !== '*');
    if (checked) {
        return [...withoutStar, name];
    }

    return withoutStar.filter((e) => e !== name);
}

function EventsFieldset(props: {
    idPrefix: string;
    allowedEvents: string[];
    value: string[];
    onChange: (next: string[]) => void;
    error?: string;
}) {
    const options = useMemo(() => [...props.allowedEvents, '*'], [props.allowedEvents]);

    return (
        <fieldset className="space-y-2">
            <legend className="text-sm font-medium">Events</legend>
            <p className="text-xs text-muted-foreground">
                Choose specific events or &quot;*&quot; for all catalog events.
            </p>
            <div className="grid gap-2 sm:grid-cols-2">
                {options.map((ev) => (
                    <label
                        key={ev}
                        className="flex cursor-pointer items-center gap-2 rounded-md border border-border px-3 py-2 text-sm"
                    >
                        <Checkbox
                            id={`${props.idPrefix}-${ev}`}
                            checked={props.value.includes(ev)}
                            onCheckedChange={(c) =>
                                props.onChange(toggleEvent(props.value, ev, c === true))
                            }
                        />
                        <span className="font-mono text-xs">{ev === '*' ? 'All (*)' : ev}</span>
                    </label>
                ))}
            </div>
            {props.error ? <InputError message={props.error} /> : null}
        </fieldset>
    );
}

export default function CompanyWebhooks() {
    const page = usePage<{
        endpoints: WebhookEndpointRow[];
        allowedEvents: string[];
        recentDeliveries: DeliveryRow[];
        oneTimeSigningSecret?: string | null;
        flash?: { status?: string | null };
    }>();

    const { endpoints, allowedEvents, recentDeliveries, oneTimeSigningSecret, flash } = page.props;
    const pageErrors = page.props.errors as Record<string, string> | undefined;

    const createForm = useForm({
        url: 'https://',
        environment: 'sandbox' as 'sandbox' | 'live',
        events: [] as string[],
        is_active: true,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Webhooks', href: company.webhooks.index.url() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Webhooks" />

            <CompanySettingsLayout>
            <div className="space-y-8">
                <Heading
                    variant="small"
                    title="Webhook endpoints"
                    description="Subscribe to Budera events. Deliveries are HMAC-signed (SHA-256) in the Signature header."
                />

                {flash?.status ? (
                    <p className="rounded-lg border border-border bg-muted/50 px-3 py-2 text-sm" role="status">
                        {flash.status}
                    </p>
                ) : null}

                {pageErrors?.test ? (
                    <p
                        className="rounded-lg border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                        role="alert"
                    >
                        {pageErrors.test}
                    </p>
                ) : null}

                {oneTimeSigningSecret ? (
                    <div className="rounded-lg border border-border bg-muted/50 p-4">
                        <p className="text-sm font-medium">Copy this signing secret now (shown once):</p>
                        <code className="mt-2 block break-all rounded-md border border-border bg-background px-3 py-2 text-xs">
                            {oneTimeSigningSecret}
                        </code>
                    </div>
                ) : null}

                <section className="space-y-4 rounded-lg border border-border p-4">
                    <h2 className="text-sm font-semibold">Add endpoint</h2>
                    <Form
                        action={company.webhooks.store.url()}
                        method="post"
                        className="space-y-4"
                        onSuccess={() =>
                            createForm.reset('url', 'events', 'environment', 'is_active')
                        }
                    >
                        {({ processing }) => (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="wh-url">HTTPS URL</Label>
                                    <Input
                                        id="wh-url"
                                        name="url"
                                        type="url"
                                        value={createForm.data.url}
                                        onChange={(e) => createForm.setData('url', e.target.value)}
                                        required
                                        autoComplete="off"
                                    />
                                    <InputError message={createForm.errors.url ?? pageErrors?.url} />
                                </div>

                                <div className="space-y-2">
                                    <Label>Environment</Label>
                                    <input type="hidden" name="environment" value={createForm.data.environment} />
                                    <Select
                                        value={createForm.data.environment}
                                        onValueChange={(v) =>
                                            createForm.setData('environment', v as 'sandbox' | 'live')
                                        }
                                    >
                                        <SelectTrigger className="w-full sm:w-48">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="sandbox">Sandbox</SelectItem>
                                            <SelectItem value="live">Live</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={createForm.errors.environment ?? pageErrors?.environment} />
                                </div>

                                <input type="hidden" name="is_active" value={createForm.data.is_active ? '1' : '0'} />
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={createForm.data.is_active}
                                        onCheckedChange={(c) => createForm.setData('is_active', c === true)}
                                    />
                                    Active
                                </label>

                                <EventsFieldset
                                    idPrefix="create"
                                    allowedEvents={allowedEvents}
                                    value={createForm.data.events}
                                    onChange={(events) => createForm.setData('events', events)}
                                    error={createForm.errors.events}
                                />
                                {createForm.data.events.map((ev) => (
                                    <input key={ev} type="hidden" name="events[]" value={ev} />
                                ))}

                                <Button type="submit" disabled={processing || createForm.data.events.length === 0}>
                                    {processing ? (
                                        <>
                                            <Spinner className="mr-2" />
                                            Saving…
                                        </>
                                    ) : (
                                        'Create endpoint'
                                    )}
                                </Button>
                            </>
                        )}
                    </Form>
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold">Your endpoints</h2>
                    {endpoints.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No endpoints yet.</p>
                    ) : (
                        <ul className="space-y-6">
                            {endpoints.map((ep) => (
                                <li
                                    key={ep.id}
                                    className="space-y-4 rounded-lg border border-border p-4"
                                >
                                    <EndpointUpdateForm endpoint={ep} allowedEvents={allowedEvents} />
                                    <div className="flex flex-wrap gap-2">
                                        <Form
                                            action={company.webhooks.test.url(ep.id)}
                                            method="post"
                                            className="inline"
                                        >
                                            {({ processing }) => (
                                                <Button type="submit" variant="secondary" disabled={processing}>
                                                    {processing ? (
                                                        <Spinner className="mr-2" />
                                                    ) : (
                                                        <Lightning className="mr-2 size-4" weight="duotone" />
                                                    )}
                                                    Test ping
                                                </Button>
                                            )}
                                        </Form>
                                        <Form
                                            action={company.webhooks.destroy.url(ep.id)}
                                            method="delete"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    disabled={processing}
                                                >
                                                    {processing ? (
                                                        <Spinner className="mr-2" />
                                                    ) : (
                                                        <Trash className="mr-2 size-4" weight="duotone" />
                                                    )}
                                                    Remove
                                                </Button>
                                            )}
                                        </Form>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="space-y-2">
                    <h2 className="text-sm font-semibold">Recent deliveries</h2>
                    {recentDeliveries.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No delivery attempts yet.</p>
                    ) : (
                        <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                            {recentDeliveries.map((d) => (
                                <li key={d.id} className="flex flex-col gap-1 px-3 py-2 sm:flex-row sm:justify-between">
                                    <span className="font-mono text-xs">
                                        #{d.id} · {d.event}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {d.status}
                                        {d.response_status != null ? ` · HTTP ${d.response_status}` : ''}
                                        {d.attempts > 0 ? ` · ${d.attempts} attempt(s)` : ''}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
            </CompanySettingsLayout>
        </AppLayout>
    );
}

function EndpointUpdateForm(props: {
    endpoint: WebhookEndpointRow;
    allowedEvents: string[];
}) {
    const form = useForm({
        url: props.endpoint.url,
        environment: props.endpoint.environment,
        events: [...props.endpoint.events],
        is_active: props.endpoint.is_active,
    });

    return (
        <Form
            action={company.webhooks.update.url(props.endpoint.id)}
            method="patch"
            className="space-y-4"
        >
            {({ processing }) => (
                <>
                    <div className="space-y-2">
                        <Label htmlFor={`wh-url-${props.endpoint.id}`}>HTTPS URL</Label>
                        <Input
                            id={`wh-url-${props.endpoint.id}`}
                            name="url"
                            type="url"
                            value={form.data.url}
                            onChange={(e) => form.setData('url', e.target.value)}
                            required
                        />
                        <InputError message={form.errors.url} />
                    </div>
                    <div className="space-y-2">
                        <Label>Environment</Label>
                        <input type="hidden" name="environment" value={form.data.environment} />
                        <Select
                            value={form.data.environment}
                            onValueChange={(v) => form.setData('environment', v as 'sandbox' | 'live')}
                        >
                            <SelectTrigger className="w-full sm:w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="sandbox">Sandbox</SelectItem>
                                <SelectItem value="live">Live</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.environment} />
                    </div>
                    <input type="hidden" name="is_active" value={form.data.is_active ? '1' : '0'} />
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.is_active}
                            onCheckedChange={(c) => form.setData('is_active', c === true)}
                        />
                        Active
                    </label>
                    <EventsFieldset
                        idPrefix={`edit-${props.endpoint.id}`}
                        allowedEvents={props.allowedEvents}
                        value={form.data.events}
                        onChange={(events) => form.setData('events', events)}
                        error={form.errors.events}
                    />
                    {form.data.events.map((ev) => (
                        <input key={ev} type="hidden" name="events[]" value={ev} />
                    ))}
                    <Button type="submit" disabled={processing || form.data.events.length === 0}>
                        {processing ? (
                            <>
                                <Spinner className="mr-2" />
                                Saving…
                            </>
                        ) : (
                            'Save changes'
                        )}
                    </Button>
                </>
            )}
        </Form>
    );
}
