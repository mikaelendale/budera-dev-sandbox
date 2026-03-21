import { Form, Head } from '@inertiajs/react';
import { Chrome, Facebook, Github, Lock, Mail, User } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { store } from '@/routes/register';

export default function Register() {
    return (
        <AuthLayout title="Create account">
            <Head title="Register" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="name"
                                    className="text-xs font-semibold uppercase tracking-wide text-foreground"
                                >
                                    Name
                                </Label>
                                <div className="relative">
                                    <User
                                        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden
                                    />
                                    <Input
                                        id="name"
                                        type="text"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="name"
                                        name="name"
                                        placeholder="Full name"
                                        className="h-11 rounded-2xl border-border/80 bg-background pl-10 pr-3"
                                    />
                                </div>
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

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
                                        required
                                        tabIndex={2}
                                        autoComplete="email"
                                        name="email"
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
                                        required
                                        tabIndex={3}
                                        autoComplete="new-password"
                                        name="password"
                                        placeholder="••••••••"
                                        className="h-11 rounded-2xl border-border/80 bg-background pl-10"
                                    />
                                </div>
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor="password_confirmation"
                                    className="text-xs font-semibold uppercase tracking-wide text-foreground"
                                >
                                    Confirm password
                                </Label>
                                <div className="relative">
                                    <Lock
                                        className="pointer-events-none absolute left-3 top-1/2 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden
                                    />
                                    <PasswordInput
                                        id="password_confirmation"
                                        required
                                        tabIndex={4}
                                        autoComplete="new-password"
                                        name="password_confirmation"
                                        placeholder="••••••••"
                                        className="h-11 rounded-2xl border-border/80 bg-background pl-10"
                                    />
                                </div>
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="h-11 w-full rounded-2xl bg-zinc-950 text-white hover:bg-zinc-900 dark:bg-zinc-50 dark:text-zinc-950 dark:hover:bg-white"
                                tabIndex={5}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create account
                            </Button>
                        </div>

                        <div className="space-y-4">
                            <div className="text-center text-sm text-muted-foreground">
                                Already have an account?{' '}
                                <TextLink href={login()} tabIndex={6}>
                                    Sign in
                                </TextLink>
                            </div>

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
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
