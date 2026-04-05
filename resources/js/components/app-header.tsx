import { Link, usePage } from '@inertiajs/react';
import {
    Bank,
    BookOpen,
    Buildings,
    CaretDown,
    CurrencyCircleDollar,
    FileText,
    Gear,
    Key,
    Lightning,
    List,
    Robot,
    Scales,
    ShieldCheck,
    SquaresFour,
    Wallet,
    WebhooksLogo,
} from '@phosphor-icons/react';
import { useMemo } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { TestModeBanner } from '@/components/test-mode-banner';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import adminRoutes from '@/routes/admin';
import bankPartnerRoutes from '@/routes/bank-partner';
import companyRoutes from '@/routes/company';
import userRoutes from '@/routes/user';
import type { BreadcrumbItem, NavItem, SharedCompany } from '@/types';
import { ModeToggle } from './mode-toggle';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const rightNavItems: NavItem[] = [
    {
        title: 'Docs',
        href: '/docs',
        icon: BookOpen,
    },
];

const activeItemStyles =
    'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100';
const myWalletHref = '/my-wallet';

export function AppHeader({ breadcrumbs = [] }: Props) {
    const page = usePage();
    const {
        auth,
        company,
        isBuderaAdmin,
        isBankPartner,
        isEndUser,
        dashboardEnvironment,
        companyLiveEnabled,
        canSwitchDashboardEnvironment,
    } = page.props as typeof page.props & {
        company: SharedCompany;
        isBuderaAdmin?: boolean;
        isBankPartner?: boolean;
        isEndUser?: boolean;
        dashboardEnvironment: 'sandbox' | 'live' | null;
        companyLiveEnabled: boolean;
        canSwitchDashboardEnvironment: boolean;
    };
    const getInitials = useInitials();
    const { isCurrentUrl, whenCurrentUrl } = useCurrentUrl();
    const cleanupMobileNav = useMobileNavigation();

    const hasCompany = company != null;
    const isKycVerified = (page.props as Record<string, unknown>).isKycVerified as boolean | undefined;
    const primaryNavItems = useMemo<NavItem[]>(() => {
        if (isEndUser) {
            if (!isKycVerified) {
                return [
                    { title: 'Verify Identity', href: '/verify-identity', icon: ShieldCheck },
                ];
            }
            return [
                { title: 'My Wallet', href: myWalletHref, icon: Wallet },
                {
                    title: 'My Agents',
                    href: userRoutes.agents.index.url(),
                    icon: Robot,
                },
            ];
        }

        if (isBankPartner && !hasCompany && !isBuderaAdmin) {
            return [
                {
                    title: 'Overview',
                    href: bankPartnerRoutes.dashboard.url(),
                    icon: SquaresFour,
                },
            ];
        }

        return [{ title: 'Dashboard', href: dashboard(), icon: SquaresFour }];
    }, [hasCompany, isBankPartner, isBuderaAdmin, isEndUser, isKycVerified]);
    const homeHref = primaryNavItems[0]?.href ?? dashboard();

    const companyItems = useMemo<NavItem[]>(() => {
        if (!hasCompany || isEndUser) return [];
        return [
            { title: 'Settings', href: companyRoutes.settings.url(), icon: Gear },
            { title: 'Wallets', href: companyRoutes.wallets.index.url(), icon: Wallet },
            { title: 'API Keys', href: companyRoutes.apiKeys.index.url(), icon: Key },
            { title: 'Webhooks', href: companyRoutes.webhooks.index.url(), icon: WebhooksLogo },
            { title: 'OAuth Apps', href: companyRoutes.oauthApps.index.url(), icon: ShieldCheck },
        ];
    }, [hasCompany, isEndUser]);

    const adminItems = useMemo<NavItem[]>(() => {
        if (!isBuderaAdmin || isEndUser) return [];
        return [
            { title: 'Companies', href: adminRoutes.companies.index.url(), icon: Buildings },
            { title: 'KYB Reviews', href: adminRoutes.kybReviews.index.url(), icon: FileText },
            { title: 'Live Access', href: adminRoutes.liveAccess.index.url(), icon: Lightning },
            { title: 'Compliance', href: adminRoutes.compliance.index.url(), icon: Scales },
            { title: 'Partner Banks', href: adminRoutes.partnerBanks.index.url(), icon: Bank },
        ];
    }, [isBuderaAdmin, isEndUser]);

    const bankPartnerItems = useMemo<NavItem[]>(() => {
        if (!isBankPartner || isEndUser) return [];
        return [
            { title: 'Overview', href: bankPartnerRoutes.dashboard.url(), icon: SquaresFour },
            { title: 'Transactions', href: bankPartnerRoutes.transactions.index.url(), icon: CurrencyCircleDollar },
            { title: 'KYB Documents', href: bankPartnerRoutes.kybDocuments.index.url(), icon: FileText },
            { title: 'Reconciliation', href: bankPartnerRoutes.reconciliation.index.url(), icon: Wallet },
        ];
    }, [isBankPartner, isEndUser]);

    const isAnyCompanyUrlActive = companyItems.some((item) => isCurrentUrl(item.href));
    const isAnyAdminUrlActive = adminItems.some((item) => isCurrentUrl(item.href));
    const isAnyBankPartnerUrlActive = bankPartnerItems.some((item) => isCurrentUrl(item.href));

    return (
        <>
            <div className="">
                <div className="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                    {/* Mobile Menu */}
                    <div className="lg:hidden">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="mr-2 h-[34px] w-[34px]"
                                >
                                    <List className="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                className="flex h-full w-64 flex-col items-stretch justify-between overflow-y-auto bg-sidebar"
                            >
                                <SheetTitle className="sr-only">
                                    Navigation menu
                                </SheetTitle>
                                <SheetHeader className="flex justify-start text-left">
                                    <AppLogoIcon className="h-6 w-6 fill-current text-black dark:text-white" />
                                </SheetHeader>
                                <div className="flex h-full flex-1 flex-col space-y-4 p-4">
                                    <div className="flex h-full flex-col justify-between text-sm">
                                        <div className="flex flex-col space-y-1">
                                            {primaryNavItems.map((item) => (
                                                <Link
                                                    key={item.title}
                                                    href={item.href}
                                                    onClick={cleanupMobileNav}
                                                    className="flex items-center space-x-2 rounded-md px-2 py-2 font-medium hover:bg-accent"
                                                >
                                                    {item.icon && (
                                                        <item.icon className="h-5 w-5" />
                                                    )}
                                                    <span>{item.title}</span>
                                                </Link>
                                            ))}

                                            {companyItems.length > 0 && (
                                                <>
                                                    <div className="px-2 pt-4 pb-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                        Company
                                                    </div>
                                                    {companyItems.map((item) => (
                                                        <Link
                                                            key={item.title}
                                                            href={item.href}
                                                            onClick={cleanupMobileNav}
                                                            className="flex items-center space-x-2 rounded-md px-2 py-2 font-medium hover:bg-accent"
                                                        >
                                                            {item.icon && <item.icon className="h-5 w-5" />}
                                                            <span>{item.title}</span>
                                                        </Link>
                                                    ))}
                                                </>
                                            )}

                                            {adminItems.length > 0 && (
                                                <>
                                                    <div className="px-2 pt-4 pb-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                        Admin
                                                    </div>
                                                    {adminItems.map((item) => (
                                                        <Link
                                                            key={item.title}
                                                            href={item.href}
                                                            onClick={cleanupMobileNav}
                                                            className="flex items-center space-x-2 rounded-md px-2 py-2 font-medium hover:bg-accent"
                                                        >
                                                            {item.icon && <item.icon className="h-5 w-5" />}
                                                            <span>{item.title}</span>
                                                        </Link>
                                                    ))}
                                                </>
                                            )}

                                            {bankPartnerItems.length > 0 && (
                                                <>
                                                    <div className="px-2 pt-4 pb-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                        Bank Partner
                                                    </div>
                                                    {bankPartnerItems.map((item) => (
                                                        <Link
                                                            key={item.title}
                                                            href={item.href}
                                                            onClick={cleanupMobileNav}
                                                            className="flex items-center space-x-2 rounded-md px-2 py-2 font-medium hover:bg-accent"
                                                        >
                                                            {item.icon && <item.icon className="h-5 w-5" />}
                                                            <span>{item.title}</span>
                                                        </Link>
                                                    ))}
                                                </>
                                            )}
                                        </div>

                                        <div className="flex flex-col space-y-1 pt-4">
                                            {rightNavItems.map((item) => (
                                                <Link
                                                    key={item.title}
                                                    href={item.href}
                                                    onClick={cleanupMobileNav}
                                                    className="flex items-center space-x-2 rounded-md px-2 py-2 font-medium hover:bg-accent"
                                                >
                                                    {item.icon && <item.icon className="h-5 w-5" />}
                                                    <span>{item.title}</span>
                                                </Link>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>

                    <Link
                        href={homeHref}
                        prefetch
                        className="flex items-center space-x-2"
                    >
                        <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                            <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                        </div>
                    </Link>

                    {/* Desktop Navigation */}
                    <div className="ml-6 hidden h-full items-center space-x-2 lg:flex">
                        <NavigationMenu className="flex h-full items-stretch">
                            <NavigationMenuList className="flex h-full items-stretch space-x-1">
                                {primaryNavItems.map((item) => (
                                    <NavigationMenuItem
                                        key={item.title}
                                        className="relative flex h-full items-center"
                                    >
                                        <Link
                                            href={item.href}
                                            className={cn(
                                                navigationMenuTriggerStyle(),
                                                whenCurrentUrl(
                                                    item.href,
                                                    activeItemStyles,
                                                ),
                                                `h-9 cursor-pointer px-3 ${isCurrentUrl(item.href) ? 'bg-accent' : ''}`,
                                            )}
                                        >
                                            {item.icon && (
                                                <item.icon className="mr-2 h-4 w-4" />
                                            )}
                                            {item.title}
                                        </Link>
                                        {isCurrentUrl(item.href) && (
                                            <div className="absolute bottom-0 left-0 h-0.5 w-full bg-primary" />
                                        )}
                                    </NavigationMenuItem>
                                ))}

                                {/* Company Dropdown */}
                                {companyItems.length > 0 && (
                                    <NavigationMenuItem className="relative flex h-full items-center">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <button
                                                    className={cn(
                                                        navigationMenuTriggerStyle(),
                                                        isAnyCompanyUrlActive && activeItemStyles,
                                                        `h-9 cursor-pointer px-3 ${isAnyCompanyUrlActive ? 'bg-accent' : ''}`,
                                                    )}
                                                >
                                                    <Buildings className="mr-2 h-4 w-4" />
                                                    Company
                                                    <CaretDown className="ml-1 h-3 w-3" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="start" className="w-48">
                                                <DropdownMenuLabel className="truncate font-normal text-xs text-muted-foreground">
                                                    {company?.name}
                                                </DropdownMenuLabel>
                                                <DropdownMenuSeparator />
                                                {companyItems.map((item) => (
                                                    <DropdownMenuItem key={item.title} asChild>
                                                        <Link
                                                            href={item.href}
                                                            className="flex cursor-pointer items-center"
                                                        >
                                                            {item.icon && <item.icon className="mr-2 h-4 w-4" />}
                                                            {item.title}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                ))}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                        {isAnyCompanyUrlActive && (
                                            <div className="absolute bottom-0 left-0 h-0.5 w-full bg-primary" />
                                        )}
                                    </NavigationMenuItem>
                                )}

                                {/* Admin Dropdown */}
                                {adminItems.length > 0 && (
                                    <NavigationMenuItem className="relative flex h-full items-center">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <button
                                                    className={cn(
                                                        navigationMenuTriggerStyle(),
                                                        isAnyAdminUrlActive && activeItemStyles,
                                                        `h-9 cursor-pointer px-3 ${isAnyAdminUrlActive ? 'bg-accent' : ''}`,
                                                    )}
                                                >
                                                    <Scales className="mr-2 h-4 w-4" />
                                                    Admin
                                                    <CaretDown className="ml-1 h-3 w-3" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="start" className="w-48">
                                                {adminItems.map((item) => (
                                                    <DropdownMenuItem key={item.title} asChild>
                                                        <Link
                                                            href={item.href}
                                                            className="flex cursor-pointer items-center"
                                                        >
                                                            {item.icon && <item.icon className="mr-2 h-4 w-4" />}
                                                            {item.title}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                ))}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                        {isAnyAdminUrlActive && (
                                            <div className="absolute bottom-0 left-0 h-0.5 w-full bg-primary" />
                                        )}
                                    </NavigationMenuItem>
                                )}

                                {/* Bank Partner Dropdown */}
                                {bankPartnerItems.length > 0 && (
                                    <NavigationMenuItem className="relative flex h-full items-center">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <button
                                                    className={cn(
                                                        navigationMenuTriggerStyle(),
                                                        isAnyBankPartnerUrlActive && activeItemStyles,
                                                        `h-9 cursor-pointer px-3 ${isAnyBankPartnerUrlActive ? 'bg-accent' : ''}`,
                                                    )}
                                                >
                                                    <Bank className="mr-2 h-4 w-4" />
                                                    Bank Partner
                                                    <CaretDown className="ml-1 h-3 w-3" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="start" className="w-48">
                                                {bankPartnerItems.map((item) => (
                                                    <DropdownMenuItem key={item.title} asChild>
                                                        <Link
                                                            href={item.href}
                                                            className="flex cursor-pointer items-center"
                                                        >
                                                            {item.icon && <item.icon className="mr-2 h-4 w-4" />}
                                                            {item.title}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                ))}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                        {isAnyBankPartnerUrlActive && (
                                            <div className="absolute bottom-0 left-0 h-0.5 w-full bg-primary" />
                                        )}
                                    </NavigationMenuItem>
                                )}
                            </NavigationMenuList>
                        </NavigationMenu>
                    </div>

                    <div className="ml-auto flex items-center space-x-2">
                        <TestModeBanner
                            dashboardEnvironment={dashboardEnvironment}
                            companyLiveEnabled={companyLiveEnabled}
                            canSwitchDashboardEnvironment={canSwitchDashboardEnvironment}
                        />
                        <div className="relative flex items-center space-x-1">
                            <ModeToggle/>
                            <div className="ml-1 hidden gap-1 lg:flex">
                                {rightNavItems.map((item) => (
                                    <Tooltip key={item.title}>
                                        <TooltipTrigger asChild>
                                            <Link
                                                href={item.href}
                                                className="group inline-flex h-9 w-9 items-center justify-center rounded-md bg-transparent p-0 text-sm font-medium text-accent-foreground ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                                            >
                                                <span className="sr-only">
                                                    {item.title}
                                                </span>
                                                {item.icon && (
                                                    <item.icon className="size-5 opacity-80 group-hover:opacity-100" />
                                                )}
                                            </Link>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{item.title}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                ))}
                            </div>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="size-10 rounded-full p-1"
                                >
                                    <Avatar className="size-8 overflow-hidden rounded-full">
                                        <AvatarImage
                                            src={auth.user.avatar}
                                            alt={auth.user.name}
                                        />
                                        <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                            {getInitials(auth.user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56" align="end">
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>
            {breadcrumbs.length > 1 && (
                <div className="flex w-full border-b border-sidebar-border/70">
                    <div className="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}
        </>
    );
}
