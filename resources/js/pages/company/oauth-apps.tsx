import { Form, Head, useForm, usePage } from '@inertiajs/react';
import { Key, Trash } from '@phosphor-icons/react';
import { useEffect } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import AppLayout from '@/layouts/app-layout';
import CompanySettingsLayout from '@/layouts/company/layout';
import company from '@/routes/company';
import type { BreadcrumbItem } from '@/types';

type ClientRow = {
    id: string;
    name: string;
    redirect_uris: string[];
    confidential: boolean;
};

type OauthClientCredentialsFlash = {
    client_id: string;
    client_secret: string;
};

export default function CompanyOauthApps() {
    const page = usePage<{
        clients: ClientRow[];
        flash?: {
            status?: string | null;
            oauth_client_credentials?: OauthClientCredentialsFlash | null;
        };
    }>();
    const { clients, flash } = page.props;

    const [, copy] = useClipboard();

    useEffect(() => {
        if (flash?.oauth_client_credentials !== undefined && flash.oauth_client_credentials !== null) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }, [flash?.oauth_client_credentials]);

    const form = useForm({
        name: '',
        redirect_uri: '',
        is_public: false,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'OAuth Apps', href: company.oauthApps.index.url() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="OAuth applications" />

            <CompanySettingsLayout>
            <div className="space-y-8">
                <Heading
                    variant="small"
                    title="OAuth applications"
                    description="Register OAuth clients for your AI product to authorize users with Budera (authorization code + PKCE)."
                />

                {flash?.oauth_client_credentials ? (
                    <div
                        className="space-y-3 rounded-lg border border-amber-500/50 bg-amber-500/10 px-4 py-3 text-sm"
                        role="status"
                    >
                        <p className="font-medium text-amber-950 dark:text-amber-100">
                            {flash.status}
                        </p>
                        <div className="grid gap-2 font-mono text-xs">
                            <div>
                                <span className="text-muted-foreground">Client ID</span>
                                <div className="mt-1 flex flex-wrap items-center gap-2 break-all">
                                    <span>{flash.oauth_client_credentials.client_id}</span>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        className="h-7 shrink-0"
                                        onClick={() =>
                                            copy(flash.oauth_client_credentials!.client_id)
                                        }
                                    >
                                        Copy
                                    </Button>
                                </div>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Client secret</span>
                                <div className="mt-1 flex flex-wrap items-center gap-2 break-all">
                                    <span>{flash.oauth_client_credentials.client_secret}</span>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        className="h-7 shrink-0"
                                        onClick={() =>
                                            copy(flash.oauth_client_credentials!.client_secret)
                                        }
                                    >
                                        Copy
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                ) : flash?.status ? (
                    <p
                        className="rounded-lg border border-border bg-muted/50 px-3 py-2 text-sm"
                        role="status"
                    >
                        {flash.status}
                    </p>
                ) : null}

                <section className="space-y-4 rounded-xl border border-border p-6">
                    <h2 className="flex items-center gap-2 text-base font-semibold">
                        <Key className="size-5 text-muted-foreground" />
                        New client
                    </h2>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/company/oauth-apps', {
                                preserveScroll: true,
                                onSuccess: () =>
                                    form.reset(
                                        'name',
                                        'redirect_uri',
                                        'is_public',
                                    ),
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="oauth-name">Application name</Label>
                            <Input
                                id="oauth-name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                placeholder="e.g. Acme AI Agent"
                                required
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="oauth-redirect">Redirect URI</Label>
                            <Input
                                id="oauth-redirect"
                                type="url"
                                value={form.data.redirect_uri}
                                onChange={(e) =>
                                    form.setData('redirect_uri', e.target.value)
                                }
                                placeholder="https://yourapp.com/oauth/callback"
                                required
                            />
                            <InputError message={form.errors.redirect_uri} />
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="oauth-public"
                                checked={form.data.is_public}
                                onCheckedChange={(c) =>
                                    form.setData('is_public', c === true)
                                }
                            />
                            <Label htmlFor="oauth-public" className="font-normal">
                                Public client (PKCE, no client secret)
                            </Label>
                        </div>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Spinner className="mr-2 size-4" />
                            )}
                            Create client
                        </Button>
                    </form>
                </section>

                <section className="space-y-3">
                    <h2 className="text-base font-semibold">Your clients</h2>
                    {clients.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No OAuth clients yet.
                        </p>
                    ) : (
                        <div className="divide-y divide-border rounded-lg border border-border">
                            {clients.map((c) => (
                                <div
                                    key={c.id}
                                    className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-medium">{c.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            ID: {c.id}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {c.redirect_uris.join(', ') || '\u2014'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {c.confidential
                                                ? 'Confidential'
                                                : 'Public (PKCE)'}
                                        </p>
                                    </div>
                                    <Form
                                        action={`/company/oauth-apps/${c.id}`}
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
                                            Revoke
                                        </Button>
                                    </Form>
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
