import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { home } from '@/routes';
import { ModeToggle } from '@/components/mode-toggle';

export type OrionAuthMode =
    | 'login'
    | 'register'
    | 'onboarding'
    | 'confirm-password'
    | 'forgot-password'
    | 'reset-password'
    | 'verify-email'
    | 'two-factor';

const headlines: Record<OrionAuthMode, ReactNode> = {
    login: (
        <>
            We&apos;re watching the
            <br />
            darkness so you
            <br />
            don&apos;t have to
        </>
    ),
    register: (
        <>
            Join the mission.
            <br />
            Claim your seat
            <br />
            in the dark.
        </>
    ),
    onboarding: (
        <>
            Your org
            <br />
            starts here.
        </>
    ),
    'confirm-password': (
        <>
            Confirm
            <br />
            it&apos;s you.
        </>
    ),
    'forgot-password': (
        <>
            Reset
            <br />
            your access.
        </>
    ),
    'reset-password': (
        <>
            Set a new
            <br />
            password.
        </>
    ),
    'verify-email': (
        <>
            Verify
            <br />
            your email.
        </>
    ),
    'two-factor': (
        <>
            Second
            <br />
            factor.
        </>
    ),
};

type Props = {
    children: ReactNode;
    mode: OrionAuthMode;
    /** Bottom pill (e.g. switch between login / register) */
    pill?: ReactNode;
    /** Optional heading under the hero line (utility flows) */
    pageTitle?: string;
    pageDescription?: string;
    /** Terms & Privacy notice under the form (typically login + register only) */
    showTermsConsent?: boolean;
};

export default function OrionAuthLayout({
    children,
    mode,
    pill,
    pageTitle,
    pageDescription,
    showTermsConsent = false,
}: Props) {
    const { name } = usePage().props as { name: string };

    return (
        <div className="font-orion relative box-border min-h-dvh w-full overflow-hidden bg-secondary dark:bg-background text-foreground">
            {/* Grid texture — full width, fades up (same as before) */}
            <div
                className="orion-grid-bg pointer-events-none absolute inset-x-0 bottom-0 opacity-100"
                style={{
                    height: '55%',
                    maskImage:
                        'linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 100%)',
                    WebkitMaskImage:
                        'linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 100%)',
                }}
            />

            {/* Centered column — ~380px cap (tighter than max-w-md) */}
            <div className="relative z-10 flex min-h-dvh w-full flex-col items-center justify-center px-5 py-10 sm:px-8 sm:py-12 md:px-10 lg:px-12 lg:py-14 xl:px-16">
                <div className="mx-auto flex w-full max-w-[380px] flex-col items-center gap-8 text-center sm:gap-9">
                    <Link
                        href={home()}
                        className="flex shrink-0 items-center justify-center gap-2"
                    >
                        <div className="h-[26px] w-[26px] shrink-0 text-primary">
                            <svg viewBox="0 0 26 26" fill="none" aria-hidden>
                                <circle
                                    cx="13"
                                    cy="13"
                                    r="10"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeOpacity={0.7}
                                    strokeWidth="1.2"
                                />
                                <ellipse
                                    cx="13"
                                    cy="13"
                                    rx="5.5"
                                    ry="10"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeOpacity={0.45}
                                    strokeWidth="1"
                                    transform="rotate(-30 13 13)"
                                />
                                <circle
                                    cx="13"
                                    cy="13"
                                    r="3"
                                    fill="currentColor"
                                    fillOpacity={0.9}
                                />
                                <circle
                                    cx="19"
                                    cy="8"
                                    r="1.2"
                                    fill="currentColor"
                                    fillOpacity={0.65}
                                />
                            </svg>
                        </div>
                        <span
                            className="text-[17px] font-medium tracking-tight text-foreground/90"
                            style={{ letterSpacing: '-0.02em' }}
                        >
                            {name}
                        </span>
                    </Link>

                    <div className="flex w-full flex-col items-center">
                        <h1
                            className="mb-11 w-full text-[clamp(28px,3vw,38px)] leading-[1.12] font-semibold tracking-tight text-foreground"
                            style={{ letterSpacing: '-0.04em' }}
                        >
                            {headlines[mode]}
                        </h1>

                        {pageTitle ? (
                            <div className="mb-6 w-full text-left">
                                <h2 className="text-lg font-semibold tracking-tight text-foreground">
                                    {pageTitle}
                                </h2>
                                {pageDescription ? (
                                    <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                                        {pageDescription}
                                    </p>
                                ) : null}
                            </div>
                        ) : null}

                        <div className="w-full text-left">{children}</div>

                        {showTermsConsent ? (
                            <p className="mt-3.5 w-full text-center text-[11.5px] leading-relaxed text-muted-foreground">
                                By continuing, you agree to our{' '}
                                <a
                                    href="#"
                                    className="text-muted-foreground underline decoration-border underline-offset-2 hover:text-foreground"
                                >
                                    Terms and Conditions
                                </a>{' '}
                                and{' '}
                                <a
                                    href="#"
                                    className="text-muted-foreground underline decoration-border underline-offset-2 hover:text-foreground"
                                >
                                    Privacy Policy
                                </a>
                                .
                            </p>
                        ) : null}
                    </div>

                    {pill ? (
                        <div className="flex w-full items-center justify-between gap-3 rounded border border-border bg-muted/50 px-[18px] py-4">
                            {pill}
                        </div>
                    ) : null}
                </div>
            </div>
            <ModeToggle className='absolute top-4 right-4 z-10' />
        </div>
    );
}
