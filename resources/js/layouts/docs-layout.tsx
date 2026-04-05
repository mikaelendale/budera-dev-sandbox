import { Link } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { type ReactNode } from 'react';
import docs from '@/routes/docs';
import { home } from '@/routes';

type NavItem = { slug: string; label: string };

type DocsLayoutProps = {
    children: ReactNode;
    nav: NavItem[];
    current: string;
};

export default function DocsLayout({ children, nav, current }: DocsLayoutProps) {
    return (
        <div className="min-h-screen bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
            <header className="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 text-sm font-semibold tracking-tight text-neutral-800 dark:text-neutral-100"
                    >
                        <BookOpen className="size-5 text-emerald-600 dark:text-emerald-400" />
                        Budera docs
                    </Link>
                    <Link
                        href={home()}
                        className="text-sm text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100"
                    >
                        Back to app
                    </Link>
                </div>
            </header>
            <div className="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-8 md:flex-row md:gap-12">
                <nav
                    className="shrink-0 md:w-52"
                    aria-label="Documentation"
                >
                    <ul className="space-y-1 text-sm">
                        {nav.map((item) => (
                            <li key={item.slug}>
                                <Link
                                    href={docs.show.url(item.slug)}
                                    className={
                                        item.slug === current
                                            ? 'block rounded-md bg-emerald-600/10 px-3 py-2 font-medium text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300'
                                            : 'block rounded-md px-3 py-2 text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800'
                                    }
                                >
                                    {item.label}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>
                <main className="min-w-0 flex-1">{children}</main>
            </div>
        </div>
    );
}
