import { Head, useForm, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle,
    IdentificationCard,
    ShieldCheck,
    SpinnerGap,
} from '@phosphor-icons/react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    step: 'identity' | 'verifying' | 'approved';
    walletPublicId: string | null;
    flash?: { status?: string | null; error?: string | null };
};

export default function VerifyIdentity() {
    const { step, walletPublicId } = usePage<PageProps>().props;
    const flash = usePage<PageProps>().props.flash ?? {};

    const { data, setData, post, processing, errors } = useForm({
        legal_name: '',
        date_of_birth: '',
        address_line_1: '',
        city: '',
        state: '',
        zip: '',
        ssn_last4: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Verify Identity', href: '/verify-identity' },
    ];

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/verify-identity');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Verify your identity" />

            <div className="mx-auto w-full max-w-2xl px-4 py-10 md:px-6">
                <div className="mb-8 text-center">
                    <div className="mx-auto mb-4 flex size-16 items-center justify-center rounded-2xl bg-primary/10">
                        <ShieldCheck className="size-8 text-primary" weight="duotone" />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight">Verify your identity</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Complete KYC verification to activate your wallet and start using Budera.
                    </p>
                </div>

                {flash.error ? (
                    <div className="mb-6 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert">
                        {flash.error}
                    </div>
                ) : null}

                {step === 'approved' ? (
                    <div className="rounded-xl border border-border bg-card p-8 text-center">
                        <CheckCircle className="mx-auto mb-4 size-16 text-green-500" weight="duotone" />
                        <h2 className="text-xl font-semibold">Identity verified</h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Your wallet is now active. You can start using Budera.
                        </p>
                        {walletPublicId ? (
                            <p className="mt-3 font-mono text-xs text-muted-foreground">
                                Wallet: {walletPublicId}
                            </p>
                        ) : null}
                        <a
                            href="/my-wallet"
                            className="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Go to My Wallet
                            <ArrowRight className="size-4" />
                        </a>
                    </div>
                ) : null}

                {step === 'verifying' ? (
                    <div className="rounded-xl border border-border bg-card p-8 text-center">
                        <SpinnerGap className="mx-auto mb-4 size-12 animate-spin text-primary" />
                        <h2 className="text-xl font-semibold">Verification in progress</h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Your identity is being verified. This usually takes a few moments.
                        </p>
                    </div>
                ) : null}

                {step === 'identity' ? (
                    <div className="rounded-xl border border-border bg-card">
                        <div className="border-b border-border px-6 py-4">
                            <div className="flex items-center gap-3">
                                <IdentificationCard className="size-5 text-muted-foreground" />
                                <div>
                                    <h2 className="font-semibold">Personal information</h2>
                                    <p className="text-xs text-muted-foreground">
                                        This information is used solely for identity verification.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <form onSubmit={handleSubmit} className="space-y-5 p-6">
                            <div className="space-y-2">
                                <Label htmlFor="legal_name">Legal full name</Label>
                                <Input
                                    id="legal_name"
                                    value={data.legal_name}
                                    onChange={(e) => setData('legal_name', e.target.value)}
                                    placeholder="Jane Doe"
                                    autoComplete="name"
                                    required
                                />
                                <InputError message={errors.legal_name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="date_of_birth">Date of birth</Label>
                                <Input
                                    id="date_of_birth"
                                    type="date"
                                    value={data.date_of_birth}
                                    onChange={(e) => setData('date_of_birth', e.target.value)}
                                    required
                                />
                                <InputError message={errors.date_of_birth} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="address_line_1">Street address</Label>
                                <Input
                                    id="address_line_1"
                                    value={data.address_line_1}
                                    onChange={(e) => setData('address_line_1', e.target.value)}
                                    placeholder="123 Main St"
                                    autoComplete="address-line1"
                                    required
                                />
                                <InputError message={errors.address_line_1} />
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                <div className="space-y-2">
                                    <Label htmlFor="city">City</Label>
                                    <Input
                                        id="city"
                                        value={data.city}
                                        onChange={(e) => setData('city', e.target.value)}
                                        placeholder="San Francisco"
                                        autoComplete="address-level2"
                                        required
                                    />
                                    <InputError message={errors.city} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="state">State</Label>
                                    <Input
                                        id="state"
                                        value={data.state}
                                        onChange={(e) => setData('state', e.target.value)}
                                        placeholder="CA"
                                        autoComplete="address-level1"
                                        maxLength={2}
                                        required
                                    />
                                    <InputError message={errors.state} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="zip">ZIP</Label>
                                    <Input
                                        id="zip"
                                        value={data.zip}
                                        onChange={(e) => setData('zip', e.target.value)}
                                        placeholder="94105"
                                        autoComplete="postal-code"
                                        maxLength={10}
                                        required
                                    />
                                    <InputError message={errors.zip} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="ssn_last4">Last 4 of SSN</Label>
                                <Input
                                    id="ssn_last4"
                                    value={data.ssn_last4}
                                    onChange={(e) => setData('ssn_last4', e.target.value)}
                                    inputMode="numeric"
                                    autoComplete="off"
                                    maxLength={4}
                                    placeholder="1234"
                                    required
                                />
                                <InputError message={errors.ssn_last4} />
                            </div>

                            <div className="rounded-lg bg-muted/50 px-4 py-3">
                                <p className="text-xs leading-relaxed text-muted-foreground">
                                    <strong className="text-foreground">Sandbox mode:</strong>{' '}
                                    In sandbox, verification is automatically approved. In production,
                                    this would be processed by our identity verification partner.
                                </p>
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-60"
                            >
                                {processing ? (
                                    <Spinner className="size-4" />
                                ) : (
                                    <>
                                        Submit verification
                                        <ArrowRight className="size-4" />
                                    </>
                                )}
                            </button>
                        </form>
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}
