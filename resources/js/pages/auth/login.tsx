import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, EnvelopeSimple, Lock } from '@phosphor-icons/react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import {
    orionFieldClass,
    orionPillButtonClass,
    orionPrimaryButtonClass,
} from '@/lib/orion-auth-classes';
import { cn } from '@/lib/utils';
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
    const emailRef = useRef<HTMLInputElement>(null);
    const passwordRef = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const [step, setStep] = useState<1 | 2>(1);
    const [passwordReveal, setPasswordReveal] = useState(false);
    const prevStepRef = useRef<1 | 2>(1);

    useEffect(() => {
        if (errors.email || errors.password) {
            setStep(2);
        }
    }, [errors.email, errors.password]);

    const passwordSurfaceOpen =
        step === 2 && (passwordReveal || Boolean(errors.password));

    useEffect(() => {
        if (step !== 2) {
            setPasswordReveal(false);
            prevStepRef.current = step;
            return;
        }
        const enteredFromStep1 = prevStepRef.current === 1;
        prevStepRef.current = 2;
        if (!enteredFromStep1) {
            setPasswordReveal(true);
            return;
        }
        setPasswordReveal(false);
        const id = requestAnimationFrame(() => {
            requestAnimationFrame(() => setPasswordReveal(true));
        });
        return () => cancelAnimationFrame(id);
    }, [step]);

    useEffect(() => {
        if (step !== 2) {
            return;
        }
        const t = window.setTimeout(() => {
            passwordRef.current?.focus();
        }, 320);
        return () => clearTimeout(t);
    }, [step]);

    function goToPassword() {
        const el = emailRef.current;
        if (!el) {
            return;
        }
        if (!data.email.trim()) {
            el.focus();
            return;
        }
        if (!el.checkValidity()) {
            el.reportValidity();
            return;
        }
        setStep(2);
    }

    return (
        <AuthLayout
            variant="orion-login"
            showTermsConsent
            orionPill={
                canRegister ? (
                    <>
                        <span className="text-[13.5px] text-muted-foreground">
                            Don&apos;t have an account yet?
                        </span>
                        <Link
                            href={register()}
                            className={orionPillButtonClass}
                        >
                            Sign up
                        </Link>
                    </>
                ) : null
            }
        >
            <Head title="Log in" />

            <p className="mb-6 text-center text-[13px] leading-relaxed text-muted-foreground">
                Sign in to your Budera account.
            </p>

            {status && (
                <div className="mb-5 rounded-md border border-primary/20 bg-primary/5 px-4 py-3 text-center text-sm font-medium text-primary">
                    {status}
                </div>
            )}

            <form
                className="flex flex-col gap-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (step === 1) {
                        goToPassword();
                        return;
                    }
                    post(store.url(), {
                        onSuccess: () => reset('password'),
                    });
                }}
            >
                <div className="grid gap-1.5">
                    <Label
                        htmlFor="email"
                        className="mb-1 block text-[13px] font-medium text-foreground/80"
                    >
                        Email address
                    </Label>
                    <div className="relative">
                        <EnvelopeSimple
                            className="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted-foreground/60"
                            weight="duotone"
                            aria-hidden
                        />
                        <Input
                            ref={emailRef}
                            id="email"
                            type="email"
                            name="email"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="email"
                            placeholder="you@company.com"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            disabled={step === 2 && !errors.email}
                            className={cn(orionFieldClass, 'pl-11')}
                        />
                    </div>
                    <InputError
                        message={errors.email}
                        className="text-destructive"
                    />
                </div>

                {step === 2 && (
                    <div
                        className={cn(
                            'grid transition-[grid-template-rows] duration-300 ease-out motion-reduce:transition-none',
                            passwordSurfaceOpen
                                ? 'grid-rows-[1fr]'
                                : 'grid-rows-[0fr]',
                        )}
                    >
                        <div className="min-h-0">
                            <div className="flex flex-col gap-5">
                                <div className="grid gap-1.5">
                                    <div className="mb-1 flex items-center justify-between">
                                        <Label
                                            htmlFor="password"
                                            className="block text-[13px] font-medium text-foreground/80"
                                        >
                                            Password
                                        </Label>
                                        {canResetPassword && (
                                            <TextLink
                                                href={request()}
                                                className="text-[12px] font-normal text-muted-foreground no-underline hover:text-foreground hover:underline"
                                                tabIndex={4}
                                            >
                                                Forgot password?
                                            </TextLink>
                                        )}
                                    </div>
                                    <div className="relative [&_button]:text-muted-foreground [&_svg]:text-muted-foreground">
                                        <Lock
                                            className="pointer-events-none absolute top-1/2 left-3.5 z-10 size-[18px] -translate-y-1/2 text-muted-foreground/60"
                                            weight="duotone"
                                            aria-hidden
                                        />
                                        <PasswordInput
                                            ref={passwordRef}
                                            id="password"
                                            name="password"
                                            required
                                            tabIndex={2}
                                            autoComplete="current-password"
                                            placeholder="Enter your password"
                                            value={data.password}
                                            onChange={(e) =>
                                                setData(
                                                    'password',
                                                    e.target.value,
                                                )
                                            }
                                            className={cn(
                                                orionFieldClass,
                                                'pl-11',
                                            )}
                                        />
                                    </div>
                                    <InputError
                                        message={errors.password}
                                        className="text-destructive"
                                    />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        id="remember"
                                        checked={data.remember}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'remember',
                                                checked === true,
                                            )
                                        }
                                        tabIndex={3}
                                        className="border-border data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground"
                                    />
                                    <Label
                                        htmlFor="remember"
                                        className="text-[13px] font-normal text-foreground/70"
                                    >
                                        Keep me signed in
                                    </Label>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                <button
                    type="submit"
                    className={cn(
                        orionPrimaryButtonClass,
                        'group gap-2 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                    )}
                    tabIndex={step === 1 ? 2 : 5}
                    disabled={processing}
                    data-test="login-button"
                >
                    {processing ? (
                        <Spinner className="mr-1 text-primary-foreground" />
                    ) : null}
                    {step === 1 ? 'Continue' : 'Sign in'}
                    {!processing && step === 1 && (
                        <ArrowRight
                            className="size-4 transition-transform group-hover:translate-x-0.5"
                            weight="bold"
                        />
                    )}
                </button>
            </form>
        </AuthLayout>
    );
}
