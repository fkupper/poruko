import * as React from 'react';
import type { LucideIcon } from 'lucide-react';
import {
    ChevronsUpDownIcon,
    HandshakeIcon,
    LandmarkIcon,
    PiggyBankIcon,
    PlusIcon,
} from 'lucide-react';

import { useLedgerStore } from '@/stores/ledgerStore';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';

/** Matches `App\Enums\SettlementMode` string values. */
export type SpaceSettlementMode = 'direct_p2p' | 'joint_clearinghouse';

const SETTLEMENT_MODE_LABEL: Record<SpaceSettlementMode, string> = {
    direct_p2p: 'P2P',
    joint_clearinghouse: 'Joint Clearing',
};

const SETTLEMENT_MODE_ICON: Record<SpaceSettlementMode, LucideIcon> = {
    direct_p2p: HandshakeIcon,
    joint_clearinghouse: LandmarkIcon,
};

export type SpaceSwitcherItem = {
    id: number;
    name: string;
    settlementMode: SpaceSettlementMode;
};

function resolveActiveSpace(
    spaces: SpaceSwitcherItem[],
    activeLedgerId: number | null,
): SpaceSwitcherItem | undefined {
    if (spaces.length === 0) {
        return undefined;
    }
    return spaces.find((s) => s.id === activeLedgerId) ?? spaces[0];
}

export function SpaceSwitcher({
    spaces,
    isLoading = false,
    isError = false,
    onRetry,
}: {
    spaces: SpaceSwitcherItem[];
    isLoading?: boolean;
    isError?: boolean;
    onRetry?: () => void;
}) {
    const { isMobile } = useSidebar();
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    const setActiveLedgerId = useLedgerStore((s) => s.setActiveLedgerId);

    const displaySpace = resolveActiveSpace(spaces, activeLedgerId);

    // Keep the ledger store aligned with the available spaces (no local mirror state).
    React.useEffect(() => {
        if (spaces.length === 0) {
            if (activeLedgerId !== null) {
                setActiveLedgerId(null);
            }
            return;
        }
        const resolved = resolveActiveSpace(spaces, activeLedgerId);
        if (resolved && resolved.id !== activeLedgerId) {
            setActiveLedgerId(resolved.id);
        }
    }, [spaces, activeLedgerId, setActiveLedgerId]);

    if (isLoading) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" className="pointer-events-none" disabled>
                        <Skeleton className="size-8 shrink-0 rounded-lg" />
                        <div className="flex flex-1 flex-col gap-1.5 text-left">
                            <Skeleton className="h-4 w-[8rem]" />
                            <Skeleton className="h-3 w-[5rem]" />
                        </div>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    if (isError) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" className="text-muted-foreground" onClick={onRetry}>
                        <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                            <PiggyBankIcon className="size-4" />
                        </div>
                        <div className="grid flex-1 text-left text-sm leading-tight">
                            <span className="truncate font-medium">Spaces unavailable</span>
                            {onRetry ? (
                                <span className="truncate text-xs">Click to retry</span>
                            ) : null}
                        </div>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    const hasSpaces = spaces.length > 0;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                <PiggyBankIcon className="size-4" />
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                {hasSpaces && displaySpace ? (
                                    <>
                                        <span className="truncate font-medium">{displaySpace.name}</span>
                                        <span className="truncate text-xs">
                                            {SETTLEMENT_MODE_LABEL[displaySpace.settlementMode]}
                                        </span>
                                    </>
                                ) : (
                                    <>
                                        <span className="truncate font-medium">No spaces</span>
                                        <span className="truncate text-xs">Add a ledger</span>
                                    </>
                                )}
                            </div>
                            <ChevronsUpDownIcon className="ml-auto" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="start"
                        side={isMobile ? 'bottom' : 'right'}
                        sideOffset={4}
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">Spaces</DropdownMenuLabel>
                        {spaces.map((space, index) => {
                            const ModeIcon = SETTLEMENT_MODE_ICON[space.settlementMode];
                            return (
                                <DropdownMenuItem
                                    key={space.id}
                                    onClick={() => {
                                        setActiveLedgerId(space.id);
                                    }}
                                    className="gap-2 p-2"
                                >
                                    <div className="flex size-6 items-center justify-center rounded-md border">
                                        <ModeIcon className="size-3.5 shrink-0" />
                                    </div>
                                    {space.name}
                                    <DropdownMenuShortcut>⌘{index + 1}</DropdownMenuShortcut>
                                </DropdownMenuItem>
                            );
                        })}
                        {spaces.length > 0 ? <DropdownMenuSeparator /> : null}
                        <DropdownMenuItem className="gap-2 p-2">
                            <div className="flex size-6 items-center justify-center rounded-md border bg-transparent">
                                <PlusIcon className="size-4" />
                            </div>
                            <div className="font-medium text-muted-foreground">Add space</div>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
