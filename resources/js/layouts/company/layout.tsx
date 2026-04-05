import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import company from '@/routes/company';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'General',
        href: company.settings.url(),
        icon: null,
    },
    {
        title: 'API Keys',
        href: company.apiKeys.index.url(),
        icon: null,
    },
    {
        title: 'Webhooks',
        href: company.webhooks.index.url(),
        icon: null,
    },
    {
        title: 'OAuth Apps',
        href: company.oauthApps.index.url(),
        icon: null,
    },
    {
        title: 'Team',
        href: company.team.url(),
        icon: null,
    },
];

export default function CompanySettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    if (typeof window === 'undefined') {
        return null;
    }

    return (
        <div className="px-4 py-6">
            <Heading
                title="Company"
                description="Manage your organization settings, keys, and integrations"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Company settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${item.href}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1">
                    <section className="space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
