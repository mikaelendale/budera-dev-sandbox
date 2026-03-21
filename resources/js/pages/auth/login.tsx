import { Form, Head } from '@inertiajs/react';
import { Chrome, Github, Facebook, Lock, Mail } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
};

export default function Login({
    status,
    canResetPassword,
    canRegister,
}: Props) {
    return (
        <AuthLayout title="Sign in">
            <Head title="Log in" />

            {status && (
                <div className="mb-6 text-center text-sm font-medium text-green-600 dark:text-green-400">
                    {status}
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="email"
                                    className="text-xs font-semibold uppercase tracking-wide text-foreground"
                                >
                                    Email address
                                </Label>
                                <div className="relative">
                                    <Mail
                                        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden
                                    />
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="email"
                                        placeholder="johndoe@gmail.com"
                                        className="h-11 rounded-2xl border-border/80 bg-background pl-10 pr-3"
                                    />
                                </div>
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor="password"
                                    className="text-xs font-semibold uppercase tracking-wide text-foreground"
                                >
                                    Password
                                </Label>
                                <div className="relative">
                                    <Lock
                                        className="pointer-events-none absolute left-3 top-1/2 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden
                                    />
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="••••••••"
                                        className="h-11 rounded-2xl border-border/80 bg-background pl-10"
                                    />
                                </div>
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label
                                    htmlFor="remember"
                                    className="text-sm font-normal text-foreground"
                                >
                                    Remember me
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="h-11 w-full rounded-2xl bg-zinc-950 text-white hover:bg-zinc-900 dark:bg-zinc-50 dark:text-zinc-950 dark:hover:bg-white"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Sign in
                            </Button>
                        </div>

                        {canRegister && (
                            <div className="space-y-3 text-center text-sm">
                                <p className="text-muted-foreground">
                                    Don&apos;t have an account?{' '}
                                    <TextLink href={register()} tabIndex={5}>
                                        Sign up
                                    </TextLink>
                                </p>
                                {canResetPassword && (
                                    <div>
                                        <TextLink
                                            href={request()}
                                            className="text-xs text-muted-foreground"
                                            tabIndex={6}
                                        >
                                            Forgot password?
                                        </TextLink>
                                    </div>
                                )}
                            </div>
                        )}

                        {!canRegister && canResetPassword && (
                            <div className="text-center text-sm">
                                <TextLink
                                    href={request()}
                                    className="text-xs text-muted-foreground"
                                    tabIndex={5}
                                >
                                    Forgot password?
                                </TextLink>
                            </div>
                        )}

                        <div className="space-y-4">
                            <div className="flex items-center gap-3">
                                <Separator className="flex-1" />
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    Or continue with
                                </span>
                                <Separator className="flex-1" />
                            </div>
                            <div className="flex justify-center gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="size-11 rounded-full border-border/80"
                                    disabled
                                    title="Google sign-in is not configured"
                                    aria-label="Google sign-in is not configured"
                                >
                                    <Chrome className="size-5 text-[#4285F4]" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="size-11 rounded-full border-border/80"
                                    disabled
                                    title="GitHub sign-in is not configured"
                                    aria-label="GitHub sign-in is not configured"
                                >
                                    <Github className="size-5" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="size-11 rounded-full border-border/80"
                                    disabled
                                    title="Facebook sign-in is not configured"
                                    aria-label="Facebook sign-in is not configured"
                                >
                                    <Facebook className="size-5 text-[#1877F2]" />
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
