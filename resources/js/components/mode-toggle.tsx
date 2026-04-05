import { Monitor, Moon, Sun } from 'lucide-react';
import type { ButtonHTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const order: Appearance[] = ['light', 'dark', 'system'];

const meta: Record<
    Appearance,
    { icon: typeof Sun; label: string }
> = {
    light: { icon: Sun, label: 'Light' },
    dark: { icon: Moon, label: 'Dark' },
    system: { icon: Monitor, label: 'System' },
};

function nextAppearance(current: Appearance): Appearance {
    const i = order.indexOf(current);
    return order[(i + 1) % order.length]!;
}

export type ModeToggleProps = Omit<
    ButtonHTMLAttributes<HTMLButtonElement>,
    'onClick'
> & {
    /** Icon size inside the button */
    iconClassName?: string;
};

export function ModeToggle({
    className,
    iconClassName,
    type = 'button',
    ...props
}: ModeToggleProps) {
    const { appearance, updateAppearance } = useAppearance();
    const { icon: Icon, label } = meta[appearance];
    const upcoming = nextAppearance(appearance);
    const upcomingLabel = meta[upcoming].label;

    return (
        <button
            type={type}
            onClick={() => updateAppearance(upcoming)}
            title={`Switch to ${upcomingLabel}`}
            aria-label={`Theme: ${label}. Switch to ${upcomingLabel}.`}
            className={cn(
                'inline-flex size-10 shrink-0 items-center justify-center rounded-lg  text-foreground transition-colors hover:bg-muted',
                className,
            )}
            {...props}
        >
            <Icon
                className={cn('size-4.5', iconClassName)}
                aria-hidden
            />
        </button>
    );
}
