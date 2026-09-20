import { Outlet, useLocation } from 'react-router-dom';

import { AppSidebar } from '@/components/app-sidebar';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbPage,
} from '@/components/ui/breadcrumb';
import { Separator } from '@/components/ui/separator';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import { useWebMcpTools } from '@/features/mcp/webmcp/useWebMcpTools';
import { useLedgerStore } from '@/stores/ledgerStore';

const ROUTE_LABELS: Record<string, string> = {
    '/': 'Overview',
    '/transactions': 'Transactions',
    '/settlement': 'Settlement',
    '/accounts': 'Accounts',
    '/recurring': 'Recurring',
    '/my-finance': 'My Finance',
    '/ai-import': 'AI Import',
    '/agent-access': 'Agent Access',
    '/account': 'Account',
    '/settings': 'Settings',
    '/settings/mcp-log': 'MCP action log',
    '/members': 'Members',
};

function breadcrumbLabel(pathname: string): string {
    return ROUTE_LABELS[pathname] ?? 'Overview';
}

export default function DashboardLayout() {
    const { pathname } = useLocation();
    const label = breadcrumbLabel(pathname);
    const activeLedgerId = useLedgerStore((state) => state.activeLedgerId);
    useWebMcpTools(activeLedgerId);

    return (
        <SidebarProvider>
            <AppSidebar />
            <SidebarInset>
                <header className="flex h-16 shrink-0 items-center gap-2">
                    <div className="flex items-center gap-2 px-4">
                        <SidebarTrigger className="-ml-1" />
                        <Separator
                            orientation="vertical"
                            className="mr-2 mt-1.5 data-[orientation=vertical]:h-4"
                        />
                        <Breadcrumb>
                            <BreadcrumbList>
                                <BreadcrumbItem>
                                    <BreadcrumbPage>{label}</BreadcrumbPage>
                                </BreadcrumbItem>
                            </BreadcrumbList>
                        </Breadcrumb>
                    </div>
                </header>
                <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
                    <Outlet />
                </div>
            </SidebarInset>
        </SidebarProvider>
    );
}
