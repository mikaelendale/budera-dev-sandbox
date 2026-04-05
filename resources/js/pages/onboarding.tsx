import { Head, router, useForm, usePage } from '@inertiajs/react';
import { BuildingOffice, LinkSimple } from '@phosphor-icons/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AuthLayout from '@/layouts/auth-layout';
import {
    orionFieldClass,
    orionPrimaryButtonClass,
} from '@/lib/orion-auth-classes';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import invitations from '@/routes/invitations';
import { store as storeCompany } from '@/routes/onboarding/company';

type OnboardingPageProps = {
    flash?: {
        status?: string | null;
        error?: string | null;
    };
    errors?: Record<string, string>;
};

export default function Onboarding() {
    const { flash, errors: pageErrors } = usePage<OnboardingPageProps>().props;
    const flashError = flash?.error;
    const flashStatus = flash?.status;

    const { data, setData, post, processing, errors } = useForm({
        name: '',
    });

    const [inviteToken, setInviteToken] = useState('');

    function submitCompany(e: React.FormEvent) {
        e.preventDefault();
        post(storeCompany.url());
    }

    function goToInvite() {
        const t = inviteToken.trim();
        if (!t) {
            return;
        }
        router.get(invitations.accept.url({ token: t }));
    }

    return (
        <AuthLayout variant="orion-onboarding">
            <Head title="Onboarding" />

            {flashStatus ? (
                <p className="mb-4 text-center text-sm text-muted-foreground">
                    {flashStatus}
                </p>
            ) : null}
            {flashError ? (
                <p className="mb-4 text-center text-sm text-destructive">
                    {flashError}
                </p>
            ) : null}

            <Tabs defaultValue="company" className="w-full">
                <TabsList
                    variant="line"
                    className="mb-6 grid w-full grid-cols-2 gap-0 rounded-lg  p-1"
                >
                    <TabsTrigger
                        value="company"
                        className="rounded-md data-[state=active]:bg-background data-[state=active]:shadow-sm"
                    >
                        <span className="inline-flex items-center gap-2">
                            <BuildingOffice
                                className="size-4 shrink-0"
                                weight="duotone"
                            />
                            Create company
                        </span>
                    </TabsTrigger>
                    <TabsTrigger
                        value="invite"
                        className="rounded-md data-[state=active]:bg-background data-[state=active]:shadow-sm"
                    >
                        <span className="inline-flex items-center gap-2">
                            <LinkSimple
                                className="size-4 shrink-0"
                                weight="duotone"
                            />
                            Invitation
                        </span>
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="company" className="mt-0 space-y-4">
                    <p className="text-sm text-muted-foreground">
                        Solo developers and teams both start here. You will be the
                        organization owner and can invite developers later.
                    </p>
                    <form onSubmit={submitCompany} className="space-y-4">
                        <div className="grid gap-2">
                            <Label
                                htmlFor="company-name"
                                className="text-[13px] text-muted-foreground"
                            >
                                Company name
                            </Label>
                            <Input
                                id="company-name"
                                name="name"
                                value={data.name}
                                className={cn(orionFieldClass)}
                                autoComplete="organization"
                                autoFocus
                                required
                                onChange={(e) => setData('name', e.target.value)}
                            />
                            <InputError message={errors.name} />
                        </div>
                        <Button
                            type="submit"
                            className={cn('w-full', orionPrimaryButtonClass)}
                            disabled={processing}
                            data-test="create-company"
                        >
                            {processing && (
                                <Spinner className="mr-2 text-primary-foreground" />
                            )}
                            Continue to dashboard
                        </Button>
                    </form>
                </TabsContent>

                <TabsContent value="invite" className="mt-0 space-y-4">
                    <p className="text-sm text-muted-foreground">
                        Paste the token from your invite link, then sign in with the
                        same email the invitation was sent to.
                    </p>
                    <div className="grid gap-2">
                        <Label
                            htmlFor="invite-token"
                            className="text-[13px] text-muted-foreground"
                        >
                            Invite token
                        </Label>
                        <Input
                            id="invite-token"
                            value={inviteToken}
                            className={cn(orionFieldClass)}
                            placeholder="token-from-email"
                            onChange={(e) => setInviteToken(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    goToInvite();
                                }
                            }}
                        />
                        <InputError message={pageErrors?.token} />
                    </div>
                    <Button
                        type="button"
                        variant="default"
                        className="w-full"
                        onClick={goToInvite}
                    >
                        Accept invitation
                    </Button>
                </TabsContent>
            </Tabs>

            <p className="mt-8 text-center text-xs text-muted-foreground">
                Already finished?{' '}
                <button
                    type="button"
                    className="text-primary underline-offset-4 hover:underline"
                    onClick={() => router.visit(dashboard.url())}
                >
                    Go to dashboard
                </button>
            </p>
        </AuthLayout>
    );
}
