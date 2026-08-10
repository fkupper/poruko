import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import QRCode from 'react-qr-code';
import { fetchLedgerMembers, createInvitation, resetTwoFactor, deactivateMember, restoreMember } from '@/api/members';
import { useLedgerStore } from '@/stores/ledgerStore';
import { useAuthStore } from '@/stores/authStore';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    UsersIcon,
    UserPlusIcon,
    MoreVerticalIcon,
    ShieldOffIcon,
    CopyIcon,
    CheckIcon,
    Loader2Icon,
    HelpCircleIcon,
    UserRoundCheckIcon,
    UserXIcon,
} from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
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
        }
    });

    const resetTwoFactorMutation = useMutation({
        mutationFn: (userId: number) => resetTwoFactor(activeLedgerId!, userId),
        onSuccess: () => {
            showFeedback('success', '2FA has been disabled for the user.');
        },
        onError: () => {
            showFeedback('error', 'Failed to reset 2FA.');
        }
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
        }
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
        }
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

    const activeMembers = members.filter(m => m.is_active !== false);
    const deactivatedMembers = members.filter(m => m.is_active === false);

    return (
        <div className="space-y-6 w-full">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <UsersIcon className="size-6 text-primary" />
                        Members
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Manage users in this space and invite new members.
                    </p>
                </div>
                <div className="flex gap-2 shrink-0">
                    <Button variant="outline" onClick={() => setIsRolesDialogOpen(true)} className="gap-2">
                        <ShieldOffIcon className="size-4" />
                        Roles & Rights
                    </Button>
                    <Button onClick={handleInviteClick} className="gap-2">
                        <UserPlusIcon className="size-4" />
                        Invite Member
                    </Button>
                </div>
            </div>

            {feedback && (
                <Alert variant={feedback.type === 'error' ? 'destructive' : 'default'} className={feedback.type === 'success' ? 'border-emerald-500 text-emerald-600' : ''}>
                    <AlertDescription>{feedback.message}</AlertDescription>
                </Alert>
            )}

            <Card className="bg-surface border-border gap-0">
                <CardHeader className="px-6 pb-3 border-b border-border">
                    <CardTitle className="text-lg font-bold text-primary flex items-center gap-2">
                        <UsersIcon className="size-5 text-info" />
                        Active Members
                    </CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="flex justify-center p-8">
                            <Loader2Icon className="size-6 animate-spin text-muted-foreground" />
                        </div>
                    ) : (
                        <div className="divide-y divide-border">
                            {activeMembers.map(member => (
                                <div key={member.id} className="px-6 py-4 flex items-center justify-between">
                                    <div>
                                        <p className="font-medium text-foreground">{member.name}</p>
                                        <p className="text-sm text-muted-foreground">{member.email}</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs font-semibold px-2 py-1 bg-secondary text-secondary-foreground rounded-full capitalize">
                                            {member.role || 'Member'}
                                        </span>
                                        {/* Only show actions if current user is an admin or owner, for now show for everyone, backend will guard */}
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon" className="h-8 w-8">
                                                    <MoreVerticalIcon className="size-4 text-muted-foreground" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem 
                                                    className="cursor-pointer"
                                                    onClick={() => resetTwoFactorMutation.mutate(member.id)}
                                                    disabled={member.is_active === false}
                                                >
                                                    <ShieldOffIcon className="size-4 mr-2" />
                                                    Reset 2FA
                                                </DropdownMenuItem>
                                                {currentUser?.id !== member.id && member.is_active !== false && (
                                                    <DropdownMenuItem 
                                                        className="text-destructive focus:bg-destructive/10 cursor-pointer"
                                                        onClick={() => setDeactivateMemberId(member.id)}
                                                    >
                                                        <ShieldOffIcon className="size-4 mr-2" />
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
                <Card className="bg-surface border-border gap-0 opacity-70">
                    <CardHeader className="px-6 pb-3 border-b border-border">
                        <CardTitle className="text-lg font-bold text-muted-foreground flex items-center gap-2">
                            <UserXIcon className="size-5" />
                            Deactivated Members
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="divide-y divide-border">
                            {deactivatedMembers.map(member => (
                                <div key={member.id} className="px-6 py-4 flex items-center justify-between gap-4">
                                    <div className="opacity-70 min-w-0">
                                        <p className="font-medium text-foreground truncate">{member.name}</p>
                                        <p className="text-sm text-muted-foreground truncate">{member.email}</p>
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                        <span className="text-xs font-semibold px-2 py-1 rounded-full capitalize bg-muted text-muted-foreground">
                                            Deactivated
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="gap-2"
                                            onClick={() => setRestoreMemberId(member.id)}
                                        >
                                            <UserRoundCheckIcon className="size-4" />
                                            Restore
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            <Dialog open={deactivateMemberId !== null} onOpenChange={(open) => !open && setDeactivateMemberId(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Deactivate Member</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to deactivate {members.find(m => m.id === deactivateMemberId)?.name}? They will immediately lose access to this space. Their historical transactions will remain intact.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="mt-4 gap-2 sm:justify-end">
                        <Button variant="ghost" onClick={() => setDeactivateMemberId(null)} disabled={deactivateMutation.isPending}>
                            Cancel
                        </Button>
                        <Button 
                            variant="destructive" 
                            onClick={() => {
                                if (deactivateMemberId) deactivateMutation.mutate(deactivateMemberId);
                            }}
                            disabled={deactivateMutation.isPending}
                        >
                            {deactivateMutation.isPending ? 'Deactivating...' : 'Deactivate Member'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={restoreMemberId !== null} onOpenChange={(open) => !open && setRestoreMemberId(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Restore Member</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to restore {members.find(m => m.id === restoreMemberId)?.name}? They will regain access to this space.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="mt-4 gap-2 sm:justify-end">
                        <Button variant="ghost" onClick={() => setRestoreMemberId(null)} disabled={restoreMutation.isPending}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => {
                                if (restoreMemberId) restoreMutation.mutate(restoreMemberId);
                            }}
                            disabled={restoreMutation.isPending}
                        >
                            {restoreMutation.isPending ? 'Restoring...' : 'Restore Member'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={isInviteDialogOpen} onOpenChange={setIsInviteDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Invite a Member</DialogTitle>
                        <DialogDescription>
                            Share this link or QR code with the person you want to invite.
                        </DialogDescription>
                    </DialogHeader>
                    
                    <div className="flex flex-col items-center justify-center p-6 gap-6 w-full max-w-full">
                        {inviteMutation.isPending ? (
                            <Loader2Icon className="size-8 animate-spin text-muted-foreground" />
                        ) : inviteToken ? (
                            <>
                                <div className="bg-white p-4 rounded-xl shadow-sm">
                                    <QRCode value={inviteLink} size={200} />
                                </div>
                                <div className="flex items-center gap-2 w-full max-w-full">
                                    <div className="flex-1 min-w-0 bg-secondary text-secondary-foreground text-sm p-2 rounded break-all select-all">
                                        {inviteLink}
                                    </div>
                                    <Button size="icon" variant="outline" onClick={handleCopy} className="shrink-0">
                                        {copied ? <CheckIcon className="size-4" /> : <CopyIcon className="size-4" />}
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
                            Review the permissions assigned to each role in this space. Members without overarching rights can still manage their own personal resources.
                        </DialogDescription>
                    </DialogHeader>

                    {isLoadingRoles ? (
                        <div className="flex justify-center p-8">
                            <Loader2Icon className="size-6 animate-spin text-muted-foreground" />
                        </div>
                    ) : (
                        <div className="overflow-auto max-h-[60vh]">
                            <TooltipProvider delayDuration={300}>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[300px]">Permission</TableHead>
                                            {roles.map(role => (
                                                <TableHead key={role.id} className="text-center">{role.name}</TableHead>
                                            ))}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {[
                                            { id: 'users', label: 'Manage All Space Users', isImplied: false, description: 'Invite, remove, and manage members of this space.' },
                                            { id: 'settings', label: 'Manage Space Settings', isImplied: false, description: 'Update the space name, currency, and other general configuration.' },
                                            { id: 'accounts', label: 'Manage All Accounts', isImplied: false, description: 'View, edit, and delete any account in the space, regardless of owner.' },
                                            { id: 'own_accounts', label: 'Manage Own Accounts', isImplied: true, description: 'Create and manage your own personal tracking accounts.' },
                                            { id: 'transactions', label: 'Manage All Transactions', isImplied: false, description: 'View, edit, and delete any transaction in the space.' },
                                            { id: 'own_transactions', label: 'Manage Own Transactions', isImplied: true, description: 'Create and manage transactions where you are the payer or participant.' },
                                            { id: 'recurring', label: 'Manage All Recurring Templates', isImplied: false, description: 'View, edit, and delete any recurring transaction blueprint.' },
                                            { id: 'own_recurring', label: 'Manage Own Recurring Templates', isImplied: true, description: 'Set up and manage your own automated recurring transactions.' },
                                            { id: 'settlements', label: 'Manage Settlements', isImplied: false, description: 'Confirm settlement cycles and adjust settlement rules.' },
                                            { id: 'ai_ingestion', label: 'Manage AI Ingestion', isImplied: false, description: 'Configure automated receipt parsing and data imports via AI.' },
                                        ].map(perm => (
                                            <TableRow key={perm.id} className={perm.isImplied ? 'bg-muted/30' : ''}>
                                                <TableCell className="font-medium">
                                                    <div className="flex items-center gap-1.5">
                                                        <span>{perm.label}</span>
                                                        {perm.isImplied && <span className="text-xs font-normal text-muted-foreground">(Personal)</span>}
                                                        <Tooltip>
                                                            <TooltipTrigger type="button" className="inline-flex cursor-help">
                                                                <HelpCircleIcon className="size-4 text-muted-foreground hover:text-foreground transition-colors" />
                                                            </TooltipTrigger>
                                                            <TooltipContent side="right">
                                                                <p className="max-w-xs">{perm.description}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </div>
                                                </TableCell>
                                                {roles.map(role => {
                                                    const hasPerm = perm.isImplied || role.permissions.includes(perm.id);
                                                    return (
                                                        <TableCell key={role.id} className="text-center">
                                                            {hasPerm ? (
                                                                <CheckIcon className="size-4 text-emerald-500 mx-auto" />
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
