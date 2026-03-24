import * as React from 'react';
import { useQuery } from '@tanstack/react-query';

import { fetchLedgers } from '@/api/ledgers';
import type { Ledger } from '@/api/types';
import { NavMain } from '@/components/nav-main';
import { NavSecondary } from '@/components/nav-secondary';
import { NavUser } from '@/components/nav-user';
import { SpaceSwitcher, type SpaceSwitcherItem } from '@/components/SpaceSwitcher/SpaceSwitcher';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader } from '@/components/ui/sidebar';
import {
    ArrowLeftRightIcon,
    BugIcon,
    HandCoinsIcon,
    LayoutDashboardIcon,
    LifeBuoyIcon,
    RepeatIcon,
    SettingsIcon,
    UserIcon,
    WalletIcon,
} from 'lucide-react';

function ledgerToSpaceItem(ledger: Ledger): SpaceSwitcherItem {
    return {
        id: ledger.id,
        name: ledger.name,
        settlementMode: ledger.settlement_mode,
    };
}

const data = {
    navSecondary: [
        {
            title: 'Help',
            url: '/',
            icon: <LifeBuoyIcon />,
        },
        {
            title: 'Report a bug',
            url: '/',
            icon: <BugIcon />,
        },
    ],
    navMain: [
        {
            title: 'Overview',
            url: '/',
            icon: <LayoutDashboardIcon />,
        },
        {
            title: 'Transactions',
            url: '/transactions',
            icon: <ArrowLeftRightIcon />,
        },
        {
            title: 'Settlement',
            url: '/settlement',
            icon: <HandCoinsIcon />,
        },
    ],
    navSpace: [
        {
            title: 'Accounts',
            url: '/accounts',
            icon: <WalletIcon />,
        },
        {
            title: 'Recurring',
            url: '/recurring',
            icon: <RepeatIcon />,
        },
        {
            title: 'My Finance',
            url: '/my-finance',
            icon: <UserIcon />,
        },
        {
            title: 'Settings',
            url: '/settings',
            icon: <SettingsIcon />,
        },
    ],
};

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    const {
        data: ledgers,
        isPending,
        isError,
        refetch,
    } = useQuery({
        queryKey: ['ledgers'],
        queryFn: fetchLedgers,
    });

    const spaces = React.useMemo(
        () => (ledgers ?? []).map(ledgerToSpaceItem),
        [ledgers],
    );

    return (
        <Sidebar variant="inset" collapsible='icon' {...props}>
            <SidebarHeader>
                <SpaceSwitcher
                    spaces={spaces}
                    isLoading={isPending}
                    isError={isError}
                    onRetry={() => void refetch()}
                />
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={data.navMain} />
                <NavMain label="Space" items={data.navSpace} />
                <NavSecondary items={data.navSecondary} className="mt-auto" />
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
