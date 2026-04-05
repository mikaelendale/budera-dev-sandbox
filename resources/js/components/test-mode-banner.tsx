import { Form } from '@inertiajs/react';
import { Flask } from '@phosphor-icons/react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import company from '@/routes/company';

type TestModeBannerProps = {
    dashboardEnvironment: 'sandbox' | 'live' | null;
    companyLiveEnabled: boolean;
    canSwitchDashboardEnvironment: boolean;
};

export function TestModeBanner({
    dashboardEnvironment,
    companyLiveEnabled,
    canSwitchDashboardEnvironment,
}: TestModeBannerProps) {
    if (dashboardEnvironment === null) {
        return null;
    }

    const isSandbox = dashboardEnvironment === 'sandbox';

    const tooltipBody = isSandbox
        ? `You're in sandbox mode — all data is simulated.${
              !companyLiveEnabled
                  ? ' Live access is unavailable until your company completes KYB review and is approved for production.'
                  : ' Switch to live to see real data.'
          }`
        : "You're using live credentials \u2014 this is production data.";

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <div
                    className={cn(
                        'inline-flex cursor-default items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium leading-none',
                        isSandbox
                            ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                            : 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
                    )}
                >
                    <Flask className="size-3.5" weight="fill" />
                    {isSandbox ? 'Test mode' : 'Live'}
                </div>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-64 text-xs leading-relaxed">
                <p>{tooltipBody}</p>
                {canSwitchDashboardEnvironment && (
                    <Form action={company.dashboard.environment.url()} method="post" className="mt-2">
                        <input
                            type="hidden"
                            name="environment"
                            value={isSandbox ? 'live' : 'sandbox'}
                        />
                        <Button type="submit" size="sm" variant="secondary" className="h-6 w-full text-xs">
                            {isSandbox ? 'Switch to live' : 'Switch to sandbox'}
                        </Button>
                    </Form>
                )}
            </TooltipContent>
        </Tooltip>
    );
}
