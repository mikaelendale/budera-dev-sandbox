import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Code,
    EnvelopeSimple,
    Lock,
    User,
    Wallet,
} from '@phosphor-icons/react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import { login } from '@/routes';
import { store } from '@/routes/register';

type UserType = 'developer' | 'end_user';

const userTypeOptions: {
    value: UserType;
    label: string;
    description: string;
    icon: typeof Code;
}[] = [
    {
        value: 'developer',
        label: 'Developer',
        description: 'Build with the Budera API',
        icon: Code,
    },
    {
        value: 'end_user',
        label: 'End User',
        description: 'Use AI agents for payments',
        icon: Wallet,
    },
];

export default function Register() {
    const nameRef = useRef<HTMLInputElement>(null);
    const emailRef = useRef<HTMLInputElement>(null);
    const passwordRef = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        user_type: 'developer' as UserType,
    });

    const [step, setStep] = useState<1 | 2>(1);
    const [passwordReveal, setPasswordReveal] = useState(false);
    const prevStepRef = useRef<1 | 2>(1);

    useEffect(() => {
        if (errors.name || errors.email || errors.password) {
            setStep(2);
        }
    }, [errors.name, errors.email, errors.password]);

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
        const nameEl = nameRef.current;
        const emailEl = emailRef.current;
        if (!nameEl || !emailEl) {
            return;
        }
        if (!data.name.trim()) {
            nameEl.focus();
            return;
        }
        if (!nameEl.checkValidity()) {
            nameEl.reportValidity();
            return;
        }
        if (!data.email.trim()) {
            emailEl.focus();
            return;
        }
        if (!emailEl.checkValidity()) {
            emailEl.reportValidity();
            return;
        }
        setStep(2);
    }

    return (
        <AuthLayout
            variant="orion-register"
            showTermsConsent
            orionPill={
                <>
                    <span className="text-[13.5px] text-muted-foreground">
                        Already have an account?
                    </span>
                    <Link href={login()} className={orionPillButtonClass}>
                        Log in
                    </Link>
                </>
            }
        >
            <Head title="Register" />

            <p className="mb-6 text-center text-[13px] leading-relaxed text-muted-foreground">
                {data.user_type === 'developer'
                    ? 'For AI companies and developers building agent products.'
                    : 'For individuals using AI agents to manage money.'}
            </p>

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
                {/* User type selector — card style */}
                <fieldset className="grid grid-cols-2 gap-3">
                    <legend className="sr-only">Account type</legend>
                    {userTypeOptions.map((opt) => {
                        const selected = data.user_type === opt.value;
                        const Icon = opt.icon;
                        return (
                            <button
                                key={opt.value}
                                type="button"
                                onClick={() => setData('user_type', opt.value)}
                                className={cn(
                                    'group relative flex flex-col items-center gap-2 rounded-lg border px-3 py-4 text-center transition-all duration-200',
                                    'hover:border-primary/40 hover:bg-primary/3',
                                    'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                                    selected
                                        ? 'border-primary/60 bg-primary/4 shadow-sm shadow-primary/10'
                                        : 'border-border bg-transparent',
                                )}
                            >
                                <div
                                    className={cn(
                                        'flex size-9 items-center justify-center rounded-md transition-colors duration-200',
                                        selected
                                            ? 'bg-primary/10 text-primary'
                                            : 'bg-muted/60 text-muted-foreground',
                                    )}
                                >
                                    <Icon
                                        className="size-[18px]"
                                        weight={
                                            selected ? 'duotone' : 'regular'
                                        }
                                    />
                                </div>
                                <div>
                                    <span
                                        className={cn(
                                            'block text-[13px] font-semibold tracking-tight transition-colors duration-200',
                                            selected
                                                ? 'text-foreground'
                                                : 'text-foreground/70',
                                        )}
                                    >
                                        {opt.label}
                                    </span>
                                    <span className="mt-0.5 block text-[11px] leading-tight text-muted-foreground">
                                        {opt.description}
                                    </span>
                                </div>
                                {/* Selection indicator dot */}
                                <span
                                    className={cn(
                                        'absolute top-2.5 right-2.5 size-2 rounded-full transition-all duration-200',
                                        selected
                                            ? 'scale-100 bg-primary opacity-100'
                                            : 'scale-0 bg-transparent opacity-0',
                                    )}
                                />
                            </button>
                        );
                    })}
                </fieldset>

                <div className="grid gap-1.5">
                    <Label
                        htmlFor="name"
                        className="mb-1 block text-[13px] font-medium text-foreground/80"
                    >
                        Full name
                    </Label>
                    <div className="relative">
                        <User
                            className="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted-foreground/60"
                            weight="duotone"
                            aria-hidden
                        />
                        <Input
                            ref={nameRef}
                            id="name"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="name"
                            name="name"
                            placeholder="Jane Doe"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            disabled={step === 2 && !errors.name}
                            className={cn(orionFieldClass, 'pl-11')}
                        />
                    </div>
                    <InputError
                        message={errors.name}
                        className="text-destructive"
                    />
                </div>

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
                            required
                            tabIndex={2}
                            autoComplete="email"
                            name="email"
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
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="password"
                                    className="mb-1 block text-[13px] font-medium text-foreground/80"
                                >
                                    Password
                                </Label>
                                <div className="relative [&_button]:text-muted-foreground [&_svg]:text-muted-foreground">
                                    <Lock
                                        className="pointer-events-none absolute top-1/2 left-3.5 z-10 size-[18px] -translate-y-1/2 text-muted-foreground/60"
                                        weight="duotone"
                                        aria-hidden
                                    />
                                    <PasswordInput
                                        ref={passwordRef}
                                        id="password"
                                        required
                                        tabIndex={3}
                                        autoComplete="new-password"
                                        name="password"
                                        placeholder="Create a password"
                                        value={data.password}
                                        onChange={(e) =>
                                            setData('password', e.target.value)
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
                        </div>
                    </div>
                )}

                <button
                    type="submit"
                    className={cn(
                        orionPrimaryButtonClass,
                        'group gap-2 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                    )}
                    tabIndex={step === 1 ? 3 : 4}
                    disabled={processing}
                    data-test="register-user-button"
                >
                    {processing ? (
                        <Spinner className="mr-1 text-primary-foreground" />
                    ) : null}
                    {step === 1 ? 'Continue' : 'Create account'}
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
