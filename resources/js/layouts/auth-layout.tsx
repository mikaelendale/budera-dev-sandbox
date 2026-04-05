import OrionAuthLayout from '@/layouts/auth/orion-auth-layout';
import type { OrionAuthMode } from '@/layouts/auth/orion-auth-layout';
import type { AuthLayoutProps } from '@/types';

const variantToMode: Record<NonNullable<AuthLayoutProps['variant']>, OrionAuthMode> = {
    'orion-login': 'login',
    'orion-register': 'register',
    'orion-onboarding': 'onboarding',
    'orion-confirm-password': 'confirm-password',
    'orion-forgot-password': 'forgot-password',
    'orion-reset-password': 'reset-password',
    'orion-verify-email': 'verify-email',
    'orion-two-factor': 'two-factor',
};

/**
 * All authentication surfaces use the Orion shell (dark grid, centered column).
 */
export default function AuthLayout({
    children,
    title,
    description,
    variant = 'orion-login',
    orionPill,
    showTermsConsent = false,
}: AuthLayoutProps) {
    const mode = variantToMode[variant];

    return (
        <OrionAuthLayout
            mode={mode}
            pill={orionPill}
            pageTitle={title}
            pageDescription={description}
            showTermsConsent={showTermsConsent}
        >
            {children}
        </OrionAuthLayout>
    );
}
