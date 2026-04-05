import { Head, router, useForm, usePage } from '@inertiajs/react';
import { EnvelopeSimple, Trash, UsersThree } from '@phosphor-icons/react';
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
import type { BreadcrumbItem } from '@/types';

type TeamMember = {
    id: number;
    name: string;
    email: string;
    role: string;
};

type PendingInvitation = {
    id: number;
    email: string;
    expires_at: string;
    created_at: string | null;
    is_expired: boolean;
};

function roleLabel(role: string): string {
    if (role === 'company_owner') {
        return 'Owner';
    }
    if (role === 'company_developer') {
        return 'Developer';
    }
    return role;
}

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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Team',
        href: company.team.url(),
    },
];

export default function CompanyTeam() {
    const page = usePage<{
        canManageInvites: boolean;
        members: TeamMember[];
        pendingInvitations: PendingInvitation[];
        flash?: { status?: string | null };
    }>();
    const { canManageInvites, members, pendingInvitations, flash } =
        page.props;

    const inviteForm = useForm({
        email: '',
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Team" />

            <h1 className="sr-only">Team</h1>

            <CompanySettingsLayout>
                <div className="space-y-8">
                    {flash?.status ? (
                        <p
                            className="rounded-lg border border-border bg-muted/50 px-3 py-2 text-sm text-foreground"
                            role="status"
                        >
                            {flash.status}
                        </p>
                    ) : null}

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <UsersThree
                                    className="size-5 text-muted-foreground"
                                    weight="duotone"
                                />
                                Team
                            </CardTitle>
                            <CardDescription>
                                People with access to this organization in
                                Budera.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-0">
                            {members.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No members yet.
                                </p>
                            ) : (
                                <div className="divide-y divide-border rounded-lg border border-border">
                                    {members.map((m) => (
                                        <div
                                            key={m.id}
                                            className="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div>
                                                <p className="font-medium text-foreground">
                                                    {m.name}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {m.email}
                                                </p>
                                            </div>
                                            <span className="inline-flex w-fit rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                                {roleLabel(m.role)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <EnvelopeSimple
                                    className="size-5 text-muted-foreground"
                                    weight="duotone"
                                />
                                Invitations
                            </CardTitle>
                            <CardDescription>
                                Invite developers by email. They receive a link
                                with a secure token; they can also paste the
                                token on the onboarding invitation tab after
                                signing in with the same email.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {canManageInvites ? (
                                <form
                                    className="space-y-3"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        inviteForm.post(
                                            company.invitations.store.url(),
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    inviteForm.reset('email'),
                                            },
                                        );
                                    }}
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="invite-email">
                                            Email address
                                        </Label>
                                        <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
                                            <Input
                                                id="invite-email"
                                                type="email"
                                                name="email"
                                                autoComplete="email"
                                                placeholder="colleague@company.com"
                                                value={inviteForm.data.email}
                                                onChange={(e) =>
                                                    inviteForm.setData(
                                                        'email',
                                                        e.target.value,
                                                    )
                                                }
                                                className="sm:flex-1"
                                            />
                                            <Button
                                                type="submit"
                                                disabled={
                                                    inviteForm.processing
                                                }
                                                className="shrink-0 sm:mt-0"
                                            >
                                                {inviteForm.processing && (
                                                    <Spinner className="mr-2 size-4" />
                                                )}
                                                Send invite
                                            </Button>
                                        </div>
                                        <InputError
                                            message={inviteForm.errors.email}
                                        />
                                    </div>
                                </form>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Only organization owners can send
                                    invitations.
                                </p>
                            )}

                            <div>
                                <h3 className="mb-2 text-sm font-medium text-foreground">
                                    Pending invitations
                                </h3>
                                {pendingInvitations.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No pending invitations.
                                    </p>
                                ) : (
                                    <div className="divide-y divide-border rounded-lg border border-border">
                                        {pendingInvitations.map((inv) => (
                                            <div
                                                key={inv.id}
                                                className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div>
                                                    <p className="font-medium text-foreground">
                                                        {inv.email}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Expires{' '}
                                                        {formatDate(
                                                            inv.expires_at,
                                                        )}
                                                        {inv.is_expired
                                                            ? ' · expired'
                                                            : ''}
                                                    </p>
                                                </div>
                                                {canManageInvites ? (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                        onClick={() =>
                                                            router.delete(
                                                                company.invitations.destroy.url(
                                                                    {
                                                                        invitation:
                                                                            inv.id,
                                                                    },
                                                                ),
                                                                {
                                                                    preserveScroll:
                                                                        true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Trash className="mr-1.5 size-4" />
                                                        Revoke
                                                    </Button>
                                                ) : null}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </CompanySettingsLayout>
        </AppLayout>
    );
}
