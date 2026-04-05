// Components
import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { orionPrimaryButtonClass } from '@/lib/orion-auth-classes';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <AuthLayout variant="orion-verify-email">
            <Head title="Email verification" />

            <p className="mb-6 text-[13px] leading-relaxed text-muted-foreground">
                We sent a link to your inbox. Open it to verify your email, or
                resend the message below.
            </p>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button
                            disabled={processing}
                            className={cn('w-full', orionPrimaryButtonClass)}
                        >
                            {processing && (
                                <Spinner className="mr-2 text-primary-foreground" />
                            )}
                            Resend verification email
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            Log out
                        </TextLink>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
