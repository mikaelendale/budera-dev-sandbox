import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { orionFieldClass, orionPrimaryButtonClass } from '@/lib/orion-auth-classes';
import { cn } from '@/lib/utils';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <AuthLayout variant="orion-confirm-password">
            <Head title="Confirm password" />

            <p className="mb-6 text-[13px] leading-relaxed text-muted-foreground">
                This is a secure area. Confirm your password to continue.
            </p>

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label
                                htmlFor="password"
                                className="text-[13px] text-muted-foreground"
                            >
                                Password
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Password"
                                autoComplete="current-password"
                                autoFocus
                                className={cn(orionFieldClass)}
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className={cn('w-full', orionPrimaryButtonClass)}
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && (
                                    <Spinner className="mr-2 text-primary-foreground" />
                                )}
                                Confirm password
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
