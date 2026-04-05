import { Form, Head, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PageProps = {
    token: string;
    companyName: string;
    environment: string;
    bankLinkStatus: string;
    step:
        | 'credentials'
        | 'verify'
        | 'success'
        | 'locked'
        | 'expired'
        | 'revoked'
        | 'unknown';
    expired: boolean;
    sandboxMicrodepositDocumentation: string | null;
    attemptsRemaining: number | null;
    accountLast4: string | null;
    credentialsAction: string;
    verifyAction: string;
    flash?: { status?: string | null; error?: string | null };
};

export default function BankLinkSession() {
    const page = usePage<PageProps>();
    const {
        companyName,
        environment,
        step,
        expired,
        sandboxMicrodepositDocumentation,
        attemptsRemaining,
        accountLast4,
        credentialsAction,
        verifyAction,
    } = page.props;
    const flash = page.props.flash ?? {};

    return (
        <>
            <Head title="Link your bank" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-muted/30 p-4">
                <Card className="w-full max-w-lg">
                    <CardHeader>
                        <CardTitle className="text-xl">Link your bank</CardTitle>
                        <CardDescription>
                            {companyName} uses Budera to connect your personal
                            bank so you can fund your agent&apos;s wallet.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {flash.status ? (
                            <p
                                className="rounded-md border border-border bg-muted/50 px-3 py-2 text-sm text-foreground"
                                role="status"
                            >
                                {flash.status}
                            </p>
                        ) : null}
                        {flash.error ? (
                            <p
                                className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                role="alert"
                            >
                                {flash.error}
                            </p>
                        ) : null}

                        {step === 'expired' || expired ? (
                            <p className="text-sm text-muted-foreground">
                                This link has expired. Ask your provider for a new
                                bank link.
                            </p>
                        ) : null}

                        {step === 'locked' ? (
                            <p className="text-sm text-muted-foreground">
                                Too many incorrect verification attempts. This
                                bank link is locked. Contact support to start
                                again.
                            </p>
                        ) : null}

                        {step === 'revoked' ? (
                            <p className="text-sm text-muted-foreground">
                                This bank link is no longer active.
                            </p>
                        ) : null}

                        {step === 'success' ? (
                            <p className="text-sm text-foreground">
                                Your bank is connected — you can now fund your
                                agent&apos;s wallet.
                            </p>
                        ) : null}

                        {step === 'credentials' && !expired ? (
                            <Form
                                action={credentialsAction}
                                method="post"
                                className="space-y-4"
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="routing_number">
                                        Routing number (9 digits)
                                    </Label>
                                    <Input
                                        id="routing_number"
                                        name="routing_number"
                                        inputMode="numeric"
                                        autoComplete="off"
                                        required
                                        maxLength={9}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="account_number">
                                        Account number
                                    </Label>
                                    <Input
                                        id="account_number"
                                        name="account_number"
                                        inputMode="numeric"
                                        autoComplete="off"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="bank_slug">
                                        Bank name (optional)
                                    </Label>
                                    <Input
                                        id="bank_slug"
                                        name="bank_slug"
                                        placeholder="e.g. chase"
                                    />
                                </div>
                                <Button type="submit" className="w-full">
                                    Continue
                                </Button>
                            </Form>
                        ) : null}

                        {step === 'verify' && !expired ? (
                            <div className="space-y-4">
                                {environment === 'live' ? (
                                    <p className="text-sm text-muted-foreground">
                                        We sent two small deposits to your
                                        account
                                        {accountLast4
                                            ? ` ending in ${accountLast4}`
                                            : ''}
                                        . They usually arrive within one
                                        business day. When you see them, enter
                                        the amounts below (in cents).
                                    </p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Sandbox mode: use the test micro-deposit
                                        amounts below (in cents).
                                    </p>
                                )}
                                {sandboxMicrodepositDocumentation ? (
                                    <p className="rounded-md bg-muted px-3 py-2 text-sm text-foreground">
                                        {sandboxMicrodepositDocumentation}
                                    </p>
                                ) : null}
                                {attemptsRemaining !== null ? (
                                    <p className="text-xs text-muted-foreground">
                                        Attempts remaining: {attemptsRemaining}
                                    </p>
                                ) : null}
                                <Form
                                    action={verifyAction}
                                    method="post"
                                    className="space-y-4"
                                >
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="space-y-2">
                                            <Label htmlFor="amount_first_cents">
                                                First amount (¢)
                                            </Label>
                                            <Input
                                                id="amount_first_cents"
                                                name="amount_first_cents"
                                                type="number"
                                                min={1}
                                                max={99}
                                                required
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="amount_second_cents">
                                                Second amount (¢)
                                            </Label>
                                            <Input
                                                id="amount_second_cents"
                                                name="amount_second_cents"
                                                type="number"
                                                min={1}
                                                max={99}
                                                required
                                            />
                                        </div>
                                    </div>
                                    <Button type="submit" className="w-full">
                                        Verify deposits
                                    </Button>
                                </Form>
                            </div>
                        ) : null}
                    </CardContent>
                    {step === 'unknown' ? (
                        <CardFooter>
                            <p className="text-sm text-muted-foreground">
                                This session is in an unexpected state. Please
                                request a new link.
                            </p>
                        </CardFooter>
                    ) : null}
                </Card>
            </div>
        </>
    );
}
