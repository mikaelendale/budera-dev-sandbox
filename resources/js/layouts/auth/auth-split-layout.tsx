import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import { cn } from '@/lib/utils';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props as { name: string };

    return (
        <div className="relative min-h-dvh bg-background lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
            {/* Form column — left */}
            <div className="flex min-h-dvh flex-col px-6 py-10 sm:px-10 lg:justify-center lg:px-12 xl:px-16">
                <div className="mx-auto w-full max-w-md flex-1 lg:flex-none">
                    <Link
                        href={home()}
                        className="mb-10 inline-flex items-center gap-2.5 font-semibold tracking-tight text-foreground"
                    >
                        <span className="flex size-10 items-center justify-center rounded-xl bg-muted/60">
                            <AppLogoIcon className="size-6 fill-current text-foreground" />
                        </span>
                        <span className="text-lg">{name}</span>
                    </Link>

                    <div className="flex flex-col gap-1 text-left">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        {description ? (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </div>

                    <div className="mt-8">{children}</div>
                </div>
            </div>

            {/* Brand column — right */}
            <div className="relative hidden p-4 lg:block lg:p-6">
                <div
                    className={cn(
                        'relative flex h-full min-h-[calc(100dvh-2rem)] flex-col justify-between overflow-hidden rounded-3xl',
                        'bg-linear-to-br from-zinc-950 via-zinc-900 to-black text-white',
                        'shadow-2xl shadow-black/20 ring-1 ring-white/10',
                    )}
                >
                    <div
                        className="pointer-events-none absolute inset-0 opacity-40 bg-[url('/images/auth/auth-background.png')] bg-cover bg-center"
                        aria-hidden
                    />

                    <div className="relative z-10 flex flex-1 flex-col px-10 pb-10 pt-12 xl:px-14 xl:pt-16">
                        <div className="flex flex-1 flex-col items-center text-center">
                            <div className="mb-6 flex size-28 items-center justify-center rounded-3xl bg-white/5 ring-1 ring-white/10 backdrop-blur-sm xl:size-36">
                                <AppLogoIcon className="size-20 fill-white opacity-95 xl:size-24" />
                            </div>
                            <p className="text-xs font-medium uppercase tracking-[0.2em] text-white/50">
                                {name}
                            </p>
                            <h2 className="mt-4 max-w-md text-balance text-3xl font-semibold tracking-tight xl:text-4xl">
                                Welcome to {name}
                            </h2>
                            <p className="mt-4 max-w-md text-pretty text-sm leading-relaxed text-white/70">
                                Ship organized dashboards and tools your team can
                                maintain — clear structure, solid patterns, room to
                                grow.
                            </p>
                        </div>

                        <div className="relative z-10 mt-10 rounded-2xl bg-white/5 p-6 ring-1 ring-white/10 backdrop-blur-md">
                            <p className="text-lg font-medium leading-snug">
                                Ready when you are — create an account in minutes.
                            </p>
                            <p className="mt-2 text-sm text-white/60">
                                Be among the first to experience a smoother path from
                                idea to production.
                            </p>
                            <div className="mt-5 flex items-center gap-2">
                                <div className="flex -space-x-2">
                                    {[
                                        'bg-amber-500/80',
                                        'bg-emerald-500/80',
                                        'bg-sky-500/80',
                                        'bg-violet-500/80',
                                    ].map((bg, i) => (
                                        <span
                                            key={i}
                                            className={cn(
                                                'inline-flex size-9 rounded-full ring-2 ring-zinc-900',
                                                bg,
                                            )}
                                        />
                                    ))}
                                </div>
                                <span className="ml-1 rounded-full bg-white/10 px-2.5 py-1 text-xs font-medium text-white/80">
                                    +2
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
