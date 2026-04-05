import { Form, Head, usePage } from '@inertiajs/react';
import { Trash } from '@phosphor-icons/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type TokenRow = {
    id: string;
    name: string | null;
    scopes: string[];
    created_at: string | null;
    expires_at: string | null;
    client_name: string | null;
};

export default function OauthConnections() {
    const page = usePage<{
        tokens: TokenRow[];
        isEndUser?: boolean;
        flash?: { status?: string | null };
    }>();
    const { tokens, flash, isEndUser } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: isEndUser ? 'My Wallet' : 'Dashboard', href: isEndUser ? '/my-wallet' : dashboard() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Connected apps" />

            <SettingsLayout>
                <Heading
                    variant="small"
                    title="Connected apps"
                    description="Third-party applications you have authorized to access your Budera account via OAuth."
                />

                {flash?.status ? (
                    <p className="text-sm text-muted-foreground" role="status">
                        {flash.status}
                    </p>
                ) : null}

                {tokens.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No active OAuth connections. When you authorize an AI
                        agent or integration, it will appear here.
                    </p>
                ) : (
                    <div className="divide-y divide-border rounded-lg border border-border">
                        {tokens.map((t) => (
                            <div
                                key={t.id}
                                className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <p className="font-medium text-foreground">
                                        {t.client_name ?? t.name ?? 'Application'}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {t.scopes.join(', ') || '—'}
                                    </p>
                                </div>
                                <Form
                                    action={`/settings/oauth-connections/${t.id}`}
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
            </SettingsLayout>
        </AppLayout>
    );
}
