import { Outlet, useNavigate } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { ChevronsUpDownIcon, LogOutIcon } from 'lucide-react';

import { logout as logoutApi } from '@/api/auth';
import { useAuthStore } from '@/stores/authStore';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarRail,
    SidebarSeparator,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import { Spinner } from '@/components/ui/spinner';

function NavUser() {
    const { user, logout } = useAuthStore();
    const navigate = useNavigate();
    const { isMobile } = useSidebar();

    const mutation = useMutation({
        mutationFn: logoutApi,
        onSettled: () => {
            logout();
            navigate('/login');
        },
    });

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton size="lg">
                            <Avatar className="size-8 rounded-lg">
                                <AvatarFallback className="rounded-lg">
                                    {user?.name?.charAt(0) ?? '?'}
                                </AvatarFallback>
                            </Avatar>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-medium">{user?.name}</span>
                            </div>
                            <ChevronsUpDownIcon />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        side={isMobile ? 'bottom' : 'right'}
                        align="end"
                        sideOffset={4}
                    >
                        <DropdownMenuGroup>
                            <DropdownMenuItem
                                onClick={() => mutation.mutate()}
                                disabled={mutation.isPending}
                            >
                                {mutation.isPending ? (
                                    <Spinner data-icon="inline-start" />
                                ) : (
                                    <LogOutIcon />
                                )}
                                {mutation.isPending ? 'Signing out…' : 'Sign out'}
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

export default function DashboardLayout() {
    return (
        <SidebarProvider>
            <Sidebar>
                <SidebarHeader>
                    <div className="flex items-center gap-2 px-2 py-2">
                        <span className="text-base font-semibold">Poruko</span>
                    </div>
                </SidebarHeader>
                <SidebarSeparator />
                <SidebarContent>
                    <nav className="flex-1 p-2">
                        {/* Navigation links added here as features are built */}
                    </nav>
                </SidebarContent>
                <SidebarSeparator />
                <SidebarFooter>
                    <NavUser />
                </SidebarFooter>
                <SidebarRail />
            </Sidebar>
            <SidebarInset>
                <header className="flex h-16 shrink-0 items-center gap-2 border-b px-4">
                    <SidebarTrigger className="-ml-1" />
                </header>
                <div className="flex-1 overflow-auto">
                    <Outlet />
                </div>
            </SidebarInset>
        </SidebarProvider>
    );
}
