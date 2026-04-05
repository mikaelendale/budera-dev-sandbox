import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    FileText,
    FolderGit2,
    Key,
    Landmark,
    LayoutGrid,
    ListChecks,
    Settings,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Wallet,
    Webhook,
} from 'lucide-react';
import { useMemo } from 'react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import bankPartner from '@/routes/bank-partner';
import company from '@/routes/company';
import user from '@/routes/user';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const page = usePage<{
        company: { id: number; name: string } | null;
        isBuderaAdmin: boolean;
        isBankPartner: boolean;
        isEndUser: boolean;
    }>();
    const hasCompany = page.props.company != null;
    const isAdmin = page.props.isBuderaAdmin;
    const isBankPartnerUser = page.props.isBankPartner;
    const isEndUser = page.props.isEndUser;
    const isKycVerified = (page.props as Record<string, unknown>).isKycVerified as boolean | undefined;
    const myWalletHref = '/my-wallet';

    const platformItems = useMemo<NavItem[]>(() => {
        if (isEndUser) {
            if (!isKycVerified) {
                return [
                    { title: 'Verify Identity', href: '/verify-identity', icon: ShieldCheck },
                ];
            }
            return [
                { title: 'My Wallet', href: myWalletHref, icon: Wallet },
                { title: 'My Agents', href: user.agents.index.url(), icon: ListChecks },
            ];
        }

        if (isBankPartnerUser && !hasCompany && !isAdmin) {
            return [
                {
                    title: 'Overview',
                    href: bankPartner.dashboard.url(),
                    icon: LayoutGrid,
                },
            ];
        }

        return [{ title: 'Dashboard', href: dashboard(), icon: LayoutGrid }];
    }, [hasCompany, isAdmin, isBankPartnerUser, isEndUser, isKycVerified]);
    const homeHref = platformItems[0]?.href ?? dashboard();

    const companyItems = useMemo<NavItem[]>(() => {
        if (!hasCompany || isEndUser) return [];
        return [
            { title: 'Settings', href: company.settings.url(), icon: Settings },
            { title: 'Wallets', href: company.wallets.index.url(), icon: Wallet },
            { title: 'API keys', href: company.apiKeys.index.url(), icon: Key },
            { title: 'Webhooks', href: company.webhooks.index.url(), icon: Webhook },
            { title: 'OAuth apps', href: company.oauthApps.index.url(), icon: Shield },
        ];
    }, [hasCompany, isEndUser]);

    const adminItems = useMemo<NavItem[]>(() => {
        if (!isAdmin || isEndUser) return [];
        return [
            { title: 'Companies', href: admin.companies.index.url(), icon: Building2 },
            { title: 'KYB reviews', href: admin.kybReviews.index.url(), icon: Building2 },
            { title: 'Live access', href: admin.liveAccess.index.url(), icon: Key },
            { title: 'Compliance', href: admin.compliance.index.url(), icon: ShieldAlert },
            { title: 'Partner banks', href: admin.partnerBanks.index.url(), icon: Building2 },
        ];
    }, [isAdmin, isEndUser]);

    const bankPartnerItems = useMemo<NavItem[]>(() => {
        if (!isBankPartnerUser || isEndUser) return [];
        return [
            { title: 'Overview', href: bankPartner.dashboard.url(), icon: Landmark },
            { title: 'Transactions', href: bankPartner.transactions.index.url(), icon: ListChecks },
            { title: 'KYB documents', href: bankPartner.kybDocuments.index.url(), icon: FileText },
            { title: 'Reconciliation', href: bankPartner.reconciliation.index.url(), icon: Wallet },
        ];
    }, [isBankPartnerUser, isEndUser]);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeHref} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain label="Platform" items={platformItems} />
                <NavMain label="Company" items={companyItems} />
                <NavMain label="Admin" items={adminItems} />
                <NavMain label="Bank partner" items={bankPartnerItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
