import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { TestModeBanner } from '@/components/test-mode-banner';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const page = usePage<{
        dashboardEnvironment: 'sandbox' | 'live' | null;
        companyLiveEnabled: boolean;
        canSwitchDashboardEnvironment: boolean;
        flash?: { status?: string | null; error?: string | null };
    }>();

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <TestModeBanner
                    dashboardEnvironment={page.props.dashboardEnvironment}
                    companyLiveEnabled={page.props.companyLiveEnabled}
                    canSwitchDashboardEnvironment={
                        page.props.canSwitchDashboardEnvironment
                    }
                />
                {page.props.flash?.error ? (
                    <div
                        className="border-b border-destructive/40 bg-destructive/10 px-4 py-2 text-sm text-destructive"
                        role="alert"
                    >
                        {page.props.flash.error}
                    </div>
                ) : null}
                {children}
            </AppContent>
        </AppShell>
    );
}
