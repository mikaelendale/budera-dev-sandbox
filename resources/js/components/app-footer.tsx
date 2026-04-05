import { Link } from '@inertiajs/react';

export function AppFooter() {
    return (
        <footer className="mt-auto border-t border-sidebar-border/70">
            <div className="mx-auto flex flex-col items-center justify-between gap-3 px-4 py-4 text-xs text-muted-foreground sm:flex-row md:max-w-7xl">
                <p>&copy; {new Date().getFullYear()} Budera. All rights reserved.</p>
                <nav className="flex items-center gap-4">
                    <Link href="/terms" className="hover:text-foreground">
                        Terms
                    </Link>
                    <Link href="/privacy" className="hover:text-foreground">
                        Privacy
                    </Link>
                    <Link href="/docs" className="hover:text-foreground">
                        Documentation
                    </Link>
                </nav>
            </div>
        </footer>
    );
}
