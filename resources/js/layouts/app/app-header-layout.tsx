import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppFooter } from '@/components/app-footer';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import type { AppLayoutProps } from '@/types';

export default function AppHeaderLayout({
    children,
    breadcrumbs,
}: AppLayoutProps) {
    const page = usePage<{
        flash?: { status?: string | null; error?: string | null };
    }>();

    return (
        <AppShell variant="header">
            <AppHeader breadcrumbs={breadcrumbs} />
            {page.props.flash?.error ? (
                <div
                    className="border-b border-destructive/40 bg-destructive/10 px-4 py-2 text-sm text-destructive"
                    role="alert"
                >
                    {page.props.flash.error}
                </div>
            ) : null}
            <AppContent variant="header">{children}</AppContent>
            <AppFooter />
        </AppShell>
    );
}
