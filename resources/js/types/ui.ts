import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

export type AuthLayoutVariant =
    | 'orion-login'
    | 'orion-register'
    | 'orion-onboarding'
    | 'orion-confirm-password'
    | 'orion-forgot-password'
    | 'orion-reset-password'
    | 'orion-verify-email'
    | 'orion-two-factor';

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    /** Shown under the Orion hero headline for utility flows (optional) */
    title?: string;
    /** Shown under page title when provided */
    description?: string;
    variant?: AuthLayoutVariant;
    /** Bottom pill row (e.g. switch between login / register) */
    orionPill?: ReactNode;
    /** Show Terms / Privacy line under the form (login + register only) */
    showTermsConsent?: boolean;
};
