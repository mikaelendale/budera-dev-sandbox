import type { Auth } from '@/types/auth';
import type { SharedCompany } from '@/types/company';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            correlationId: string | null;
            auth: Auth;
            company: SharedCompany;
            sidebarOpen: boolean;
            dashboardEnvironment: 'sandbox' | 'live' | null;
            companyLiveEnabled: boolean;
            canSwitchDashboardEnvironment: boolean;
            isBuderaAdmin: boolean;
            isBankPartner: boolean;
            isEndUser: boolean;
            isKycVerified: boolean;
            [key: string]: unknown;
        };
    }
}
