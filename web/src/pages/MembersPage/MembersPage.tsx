import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import QRCode from 'react-qr-code';
import { fetchLedgerMembers, createInvitation, resetTwoFactor, deactivateMember, restoreMember } from '@/api/members';
import { useLedgerStore } from '@/stores/ledgerStore';
import { useAuthStore } from '@/stores/authStore';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
    UsersIcon,
    UserPlusIcon,
    MoreVerticalIcon,
    ShieldOffIcon,
    CopyIcon,
    CheckIcon,
    HelpCircleIcon,
    UserRoundCheckIcon,
    UserXIcon,
} from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { fetchLedgerRoles } from '@/api/roles';

export default function MembersPage() {
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    const currentUser = useAuthStore((s) => s.user);
    const queryClient = useQueryClient();

    const [isInviteDialogOpen, setIsInviteDialogOpen] = React.useState(false);
    const [isRolesDialogOpen, setIsRolesDialogOpen] = React.useState(false);
    const [inviteToken, setInviteToken] = React.useState<string | null>(null);
    const [deactivateMemberId, setDeactivateMemberId] = React.useState<number | null>(null);
    const [restoreMemberId, setRestoreMemberId] = React.useState<number | null>(null);
    const [reset2faMemberId, setReset2faMemberId] = React.useState<number | null>(null);
    const [copied, setCopied] = React.useState(false);
    const [feedback, setFeedback] = React.useState<{ type: 'success' | 'error'; message: string } | null>(null);

    const showFeedback = (type: 'success' | 'error', message: string) => {
        setFeedback({ type, message });
        setTimeout(() => setFeedback(null), 5000);
    };

    const { data: members = [], isLoading } = useQuery({
        queryKey: ['members', activeLedgerId],
        queryFn: () => fetchLedgerMembers(activeLedgerId!),
        enabled: !!activeLedgerId,
    });

    const { data: roles = [], isLoading: isLoadingRoles } = useQuery({
        queryKey: ['roles', activeLedgerId],
        queryFn: () => fetchLedgerRoles(activeLedgerId!),
        enabled: !!activeLedgerId && isRolesDialogOpen,
    });

    const inviteMutation = useMutation({
        mutationFn: () => createInvitation(activeLedgerId!),
        onSuccess: (data) => {
            setInviteToken(data.token);
        },
        onError: () => {
            showFeedback('error', 'Failed to generate invitation.');
        },
    });

    const resetTwoFactorMutation = useMutation({
        mutationFn: (userId: number) => resetTwoFactor(activeLedgerId!, userId),
        onSuccess: () => {
            showFeedback('success', '2FA has been disabled for the user.');
            setReset2faMemberId(null);
        },
        onError: () => {
            showFeedback('error', 'Failed to reset 2FA.');
        },
    });

    const deactivateMutation = useMutation({
        mutationFn: (userId: number) => deactivateMember(activeLedgerId!, userId),
        onSuccess: () => {
            showFeedback('success', 'User has been deactivated.');
            setDeactivateMemberId(null);
            queryClient.invalidateQueries({ queryKey: ['members', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
        },
        onError: () => {
            showFeedback('error', 'Failed to deactivate user.');
        },
    });

    const restoreMutation = useMutation({
        mutationFn: (userId: number) => restoreMember(activeLedgerId!, userId),
        onSuccess: () => {
            showFeedback('success', 'User has been restored.');
            setRestoreMemberId(null);
            queryClient.invalidateQueries({ queryKey: ['members', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
        },
        onError: () => {
            showFeedback('error', 'Failed to restore user.');
        },
    });

    const handleInviteClick = () => {
        setInviteToken(null);
        setIsInviteDialogOpen(true);
        inviteMutation.mutate();
    };

    const inviteLink = inviteToken ? `${window.location.origin}/register?invite=${inviteToken}` : '';

    const handleCopy = () => {
        navigator.clipboard.writeText(inviteLink);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const activeMembers = members.filter((m) => m.is_active !== false);
    const deactivatedMembers = members.filter((m) => m.is_active === false);

    return (
        <div className="flex w-full flex-col gap-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground">
                        <UsersIcon className="size-6 text-primary" />
                        Members
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Manage users in this space and invite new members.
                    </p>
                </div>
                <div className="flex shrink-0 gap-2">
                    <Button variant="outline" onClick={() => setIsRolesDialogOpen(true)} className="gap-2">
                        <ShieldOffIcon data-icon="inline-start" />
                        Roles & Rights
                    </Button>
                    <Button onClick={handleInviteClick} className="gap-2">
                        <UserPlusIcon data-icon="inline-start" />
                        Invite Member
                    </Button>
                </div>
            </div>

            {feedback && (
                <Alert
                    variant={feedback.type === 'error' ? 'destructive' : 'default'}
                    className={feedback.type === 'success' ? 'border-inflow text-inflow' : ''}
                >
                    <AlertDescription>{feedback.message}</AlertDescription>
                </Alert>
            )}

            <Card className="gap-0 border-border bg-card">
                <CardHeader className="border-b border-border px-6 pb-3">
                    <CardTitle className="flex items-center gap-2 text-lg font-bold text-primary">
                        <UsersIcon className="size-5 text-muted-foreground" />
                        Active Members
                    </CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="flex justify-center p-8">
                            <Spinner className="size-6 text-muted-foreground" />
                        </div>
                    ) : (
                        <div className="divide-y divide-border">
                            {activeMembers.map((member) => (
                                <div key={member.id} className="flex items-center justify-between px-6 py-4">
                                    <div>
                                        <p className="font-medium text-foreground">{member.name}</p>
                                        <p className="text-sm text-muted-foreground">{member.email}</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="rounded-full bg-secondary px-2 py-1 text-xs font-semibold capitalize text-secondary-foreground">
                                            {member.role || 'Member'}
                                        </span>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Actions for ${member.name}`}
                                                >
                                                    <MoreVerticalIcon className="text-muted-foreground" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    className="cursor-pointer"
                                                    onClick={() => setReset2faMemberId(member.id)}
                                                    disabled={member.is_active === false}
                                                >
                                                    <ShieldOffIcon className="mr-2 size-4" />
                                                    Reset 2FA
                                                </DropdownMenuItem>
                                                {currentUser?.id !== member.id && member.is_active !== false && (
                                                    <DropdownMenuItem
                                                        className="cursor-pointer text-destructive focus:bg-destructive/10"
                                                        onClick={() => setDeactivateMemberId(member.id)}
                                                    >
                                                        <ShieldOffIcon className="mr-2 size-4" />
                                                        Deactivate
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {deactivatedMembers.length > 0 && (
                <Card className="gap-0 border-border bg-card opacity-70">
                    <CardHeader className="border-b border-border px-6 pb-3">
                        <CardTitle className="flex items-center gap-2 text-lg font-bold text-muted-foreground">
                            <UserXIcon className="size-5" />
                            Deactivated Members
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="divide-y divide-border">
                            {deactivatedMembers.map((member) => (
                                <div key={member.id} className="flex items-center justify-between gap-4 px-6 py-4">
                                    <div className="min-w-0 opacity-70">
                                        <p className="truncate font-medium text-foreground">{member.name}</p>
                                        <p className="truncate text-sm text-muted-foreground">{member.email}</p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <span className="rounded-full bg-muted px-2 py-1 text-xs font-semibold capitalize text-muted-foreground">
                                            Deactivated
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="gap-2"
                                            onClick={() => setRestoreMemberId(member.id)}
                                        >
                                            <UserRoundCheckIcon data-icon="inline-start" />
                                            Restore
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            <AlertDialog
                open={reset2faMemberId !== null}
                onOpenChange={(open) => !open && setReset2faMemberId(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Reset two-factor authentication?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will disable 2FA for{' '}
                            {members.find((m) => m.id === reset2faMemberId)?.name}. They will need to set it up again.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={resetTwoFactorMutation.isPending}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            disabled={resetTwoFactorMutation.isPending || reset2faMemberId === null}
                            onClick={() => {
                                if (reset2faMemberId !== null) resetTwoFactorMutation.mutate(reset2faMemberId);
                            }}
                        >
                            {resetTwoFactorMutation.isPending ? 'Resetting…' : 'Reset 2FA'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog
                open={deactivateMemberId !== null}
                onOpenChange={(open) => !open && setDeactivateMemberId(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Deactivate Member</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to deactivate{' '}
                            {members.find((m) => m.id === deactivateMemberId)?.name}? They will immediately lose access
                            to this space. Their historical transactions will remain intact.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deactivateMutation.isPending}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            disabled={deactivateMutation.isPending || deactivateMemberId === null}
                            onClick={() => {
                                if (deactivateMemberId !== null) deactivateMutation.mutate(deactivateMemberId);
                            }}
                        >
                            {deactivateMutation.isPending ? 'Deactivating…' : 'Deactivate Member'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog
                open={restoreMemberId !== null}
                onOpenChange={(open) => !open && setRestoreMemberId(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Restore Member</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to restore {members.find((m) => m.id === restoreMemberId)?.name}? They
                            will regain access to this space.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={restoreMutation.isPending}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={restoreMutation.isPending || restoreMemberId === null}
                            onClick={() => {
                                if (restoreMemberId !== null) restoreMutation.mutate(restoreMemberId);
                            }}
                        >
                            {restoreMutation.isPending ? 'Restoring…' : 'Restore Member'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <Dialog open={isInviteDialogOpen} onOpenChange={setIsInviteDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Invite a Member</DialogTitle>
                        <DialogDescription>
                            Share this link or QR code with the person you want to invite.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex w-full max-w-full flex-col items-center justify-center gap-6 p-6">
                        {inviteMutation.isPending ? (
                            <Spinner className="size-8 text-muted-foreground" />
                        ) : inviteToken ? (
                            <>
                                <div className="rounded-xl border bg-card p-4 shadow-sm">
                                    <QRCode value={inviteLink} size={200} />
                                </div>
                                <div className="flex w-full max-w-full items-center gap-2">
                                    <div className="min-w-0 flex-1 break-all rounded bg-secondary p-2 text-sm text-secondary-foreground select-all">
                                        {inviteLink}
                                    </div>
                                    <Button
                                        size="icon"
                                        variant="outline"
                                        onClick={handleCopy}
                                        className="shrink-0"
                                        aria-label={copied ? 'Invite link copied' : 'Copy invite link'}
                                    >
                                        {copied ? <CheckIcon /> : <CopyIcon />}
                                    </Button>
                                </div>
                            </>
                        ) : (
                            <p className="text-sm text-destructive">Failed to generate link.</p>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={isRolesDialogOpen} onOpenChange={setIsRolesDialogOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Roles & Rights</DialogTitle>
                        <DialogDescription>
                            Review the permissions assigned to each role in this space. Members without overarching
                            rights can still manage their own personal resources.
                        </DialogDescription>
                    </DialogHeader>

                    {isLoadingRoles ? (
                        <div className="flex justify-center p-8">
                            <Spinner className="size-6 text-muted-foreground" />
                        </div>
                    ) : (
                        <div className="max-h-[60vh] overflow-auto">
                            <TooltipProvider delayDuration={300}>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[300px]">Permission</TableHead>
                                            {roles.map((role) => (
                                                <TableHead key={role.id} className="text-center">
                                                    {role.name}
                                                </TableHead>
                                            ))}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {[
                                            {
                                                id: 'users',
                                                label: 'Manage All Space Users',
                                                isImplied: false,
                                                description: 'Invite, remove, and manage members of this space.',
                                            },
                                            {
                                                id: 'settings',
                                                label: 'Manage Space Settings',
                                                isImplied: false,
                                                description:
                                                    'Update the space name, currency, and other general configuration.',
                                            },
                                            {
                                                id: 'accounts',
                                                label: 'Manage All Accounts',
                                                isImplied: false,
                                                description:
                                                    'View, edit, and delete any account in the space, regardless of owner.',
                                            },
                                            {
                                                id: 'own_accounts',
                                                label: 'Manage Own Accounts',
                                                isImplied: true,
                                                description: 'Create and manage your own personal tracking accounts.',
                                            },
                                            {
                                                id: 'transactions',
                                                label: 'Manage All Transactions',
                                                isImplied: false,
                                                description: 'View, edit, and delete any transaction in the space.',
                                            },
                                            {
                                                id: 'own_transactions',
                                                label: 'Manage Own Transactions',
                                                isImplied: true,
                                                description:
                                                    'Create and manage transactions where you are the payer or participant.',
                                            },
                                            {
                                                id: 'recurring',
                                                label: 'Manage All Recurring Templates',
                                                isImplied: false,
                                                description:
                                                    'View, edit, and delete any recurring transaction blueprint.',
                                            },
                                            {
                                                id: 'own_recurring',
                                                label: 'Manage Own Recurring Templates',
                                                isImplied: true,
                                                description:
                                                    'Set up and manage your own automated recurring transactions.',
                                            },
                                            {
                                                id: 'settlements',
                                                label: 'Manage Settlements',
                                                isImplied: false,
                                                description: 'Confirm settlement cycles and adjust settlement rules.',
                                            },
                                            {
                                                id: 'ai_ingestion',
                                                label: 'Manage AI Ingestion',
                                                isImplied: false,
                                                description:
                                                    'Configure automated receipt parsing and data imports via AI.',
                                            },
                                        ].map((perm) => (
                                            <TableRow key={perm.id} className={perm.isImplied ? 'bg-muted/30' : ''}>
                                                <TableCell className="font-medium">
                                                    <div className="flex items-center gap-1.5">
                                                        <span>{perm.label}</span>
                                                        {perm.isImplied && (
                                                            <span className="text-xs font-normal text-muted-foreground">
                                                                (Personal)
                                                            </span>
                                                        )}
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                type="button"
                                                                className="inline-flex cursor-help"
                                                            >
                                                                <HelpCircleIcon className="size-4 text-muted-foreground transition-colors hover:text-foreground" />
                                                            </TooltipTrigger>
                                                            <TooltipContent side="right">
                                                                <p className="max-w-xs">{perm.description}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </div>
                                                </TableCell>
                                                {roles.map((role) => {
                                                    const hasPerm =
                                                        perm.isImplied || role.permissions.includes(perm.id);
                                                    return (
                                                        <TableCell key={role.id} className="text-center">
                                                            {hasPerm ? (
                                                                <CheckIcon className="mx-auto size-4 text-inflow" />
                                                            ) : (
                                                                <span className="text-muted-foreground">-</span>
                                                            )}
                                                        </TableCell>
                                                    );
                                                })}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </TooltipProvider>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
