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
    sessionToken: string;
    step: 'identity' | 'verifying' | 'approved' | 'rejected' | 'expired';
    status: string;
    walletPublicId: string | null;
    submitAction: string;
    flash?: { status?: string | null; error?: string | null };
};

export default function KycSession() {
    const page = usePage<PageProps>();
    const { step, walletPublicId, submitAction } = page.props;
    const flash = page.props.flash ?? {};

    return (
        <>
            <Head title="Verify your identity" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-muted/30 p-4">
                <Card className="w-full max-w-lg">
                    <CardHeader>
                        <CardTitle className="text-xl">
                            Verify your identity
                        </CardTitle>
                        <CardDescription>
                            Complete identity verification to activate your
                            agent&apos;s wallet.
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

                        {step === 'expired' ? (
                            <p className="text-sm text-muted-foreground">
                                This verification session has expired. Please
                                request a new verification link.
                            </p>
                        ) : null}

                        {step === 'rejected' ? (
                            <p className="text-sm text-destructive">
                                Your identity verification was not approved.
                                Please contact support for assistance.
                            </p>
                        ) : null}

                        {step === 'approved' ? (
                            <p className="text-sm text-foreground">
                                Your identity is verified — your agent&apos;s
                                wallet is now active.
                                {walletPublicId ? (
                                    <span className="mt-1 block text-xs text-muted-foreground">
                                        Wallet: {walletPublicId}
                                    </span>
                                ) : null}
                            </p>
                        ) : null}

                        {step === 'verifying' ? (
                            <div className="flex flex-col items-center gap-3 py-6">
                                <div className="size-8 animate-spin rounded-full border-4 border-muted border-t-primary" />
                                <p className="text-sm text-muted-foreground">
                                    Verifying your identity&hellip;
                                </p>
                            </div>
                        ) : null}

                        {step === 'identity' ? (
                            <Form
                                action={submitAction}
                                method="post"
                                className="space-y-4"
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="legal_name">
                                        Legal full name
                                    </Label>
                                    <Input
                                        id="legal_name"
                                        name="legal_name"
                                        autoComplete="name"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="date_of_birth">
                                        Date of birth
                                    </Label>
                                    <Input
                                        id="date_of_birth"
                                        name="date_of_birth"
                                        type="date"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="address_line_1">
                                        Street address
                                    </Label>
                                    <Input
                                        id="address_line_1"
                                        name="address_line_1"
                                        autoComplete="address-line1"
                                        required
                                    />
                                </div>
                                <div className="grid grid-cols-3 gap-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="city">City</Label>
                                        <Input
                                            id="city"
                                            name="city"
                                            autoComplete="address-level2"
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="state">State</Label>
                                        <Input
                                            id="state"
                                            name="state"
                                            autoComplete="address-level1"
                                            maxLength={2}
                                            placeholder="CA"
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="zip">ZIP</Label>
                                        <Input
                                            id="zip"
                                            name="zip"
                                            autoComplete="postal-code"
                                            maxLength={10}
                                            required
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="ssn_last4">
                                        Last 4 of SSN
                                    </Label>
                                    <Input
                                        id="ssn_last4"
                                        name="ssn_last4"
                                        inputMode="numeric"
                                        autoComplete="off"
                                        maxLength={4}
                                        required
                                    />
                                </div>
                                <Button type="submit" className="w-full">
                                    Submit verification
                                </Button>
                            </Form>
                        ) : null}
                    </CardContent>
                    {step === 'expired' || step === 'rejected' ? (
                        <CardFooter>
                            <p className="text-xs text-muted-foreground">
                                If you believe this is an error, contact your
                                provider for a new verification link.
                            </p>
                        </CardFooter>
                    ) : null}
                </Card>
            </div>
        </>
    );
}
