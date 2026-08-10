import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { fetchAccounts, createAccount, updateAccount, deleteAccount } from '@/api/accounts';
import { fetchLedgers } from '@/api/ledgers';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { useAuthStore } from '@/stores/authStore';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
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
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { PlusIcon, WalletIcon, LandmarkIcon, CreditCardIcon, BanknoteIcon, MoreHorizontalIcon, PencilIcon, TrashIcon, UserXIcon, ArrowLeftRightIcon } from 'lucide-react';
import type { Account } from '@/api/types';

const ACCOUNT_TYPE_ICONS: Record<Account['type'], React.ReactNode> = {
    pool_asset: <LandmarkIcon className="size-5 text-inflow" />,
    space_expense: <WalletIcon className="size-5 text-muted-foreground" />,
    split_clearing: <LandmarkIcon className="size-5 text-muted-foreground" />,
    user_funding: <CreditCardIcon className="size-5 text-info" />,
    user_liability: <BanknoteIcon className="size-5 text-amber-500" />,
};

export default function AccountsPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    const currencySymbol = useLedgerCurrencySymbol();
    const user = useAuthStore((s) => s.user);

    const [isAddOpen, setIsAddOpen] = React.useState(false);
    const [editingId, setEditingId] = React.useState<number | null>(null);
    const [deletingId, setDeletingId] = React.useState<number | null>(null);
    const [name, setName] = React.useState('');
    const [type, setType] = React.useState<Account['type']>('user_funding');
    const [initialBalance, setInitialBalance] = React.useState(0);
    const [showDeactivatedAccounts, setShowDeactivatedAccounts] = React.useState(false);

    const resetForm = () => {
        setName('');
        setType('user_funding');
        setInitialBalance(0);
        setEditingId(null);
        setIsAddOpen(false);
    };

    const handleEdit = (acc: Account) => {
        setName(acc.name);
        setType(acc.type);
        setInitialBalance(acc.balance);
        setEditingId(acc.id);
        setIsAddOpen(true);
    };

    const { data: accounts = [], isPending } = useQuery({
        queryKey: ['accounts', activeLedgerId],
        queryFn: () => fetchAccounts(activeLedgerId!),
        enabled: !!activeLedgerId,
    });

    const { data: ledgers } = useQuery({ queryKey: ['ledgers'], queryFn: fetchLedgers });

    const saveMutation = useMutation({
        mutationFn: async () => {
            if (!activeLedgerId) throw new Error('No active space.');
            if (editingId) {
                return updateAccount(activeLedgerId, editingId, {
                    name,
                    current_funds: initialBalance,
                });
            }
            return createAccount(activeLedgerId, {
                name,
                type,
                balance: initialBalance,
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
            resetForm();
        },
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: number) => {
            if (!activeLedgerId) throw new Error('No active space.');
            return deleteAccount(activeLedgerId, id);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
            setDeletingId(null);
        },
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        saveMutation.mutate();
    };

    const myAccounts = accounts.filter((a) => (a.type === 'user_funding' || a.type === 'user_liability') && a.owner_id === user?.id);
    const poolAssets = accounts.filter((a) => a.type === 'pool_asset');
    const spaceExpenses = accounts.filter((a) => a.type === 'space_expense');
    const systemAccounts = accounts.filter((a) => a.type === 'split_clearing');
    
    const otherUsersAccounts = accounts.filter((a) => {
        if (a.type !== 'user_funding' && a.type !== 'user_liability') return false;
        if (a.owner_id === user?.id || a.owner_id === null) return false;
        if (!showDeactivatedAccounts && a.owner_is_active === false) return false;
        return true;
    });

    const hasAnyOtherMemberAccounts = accounts.some((a) => 
        (a.type === 'user_funding' || a.type === 'user_liability') && 
        a.owner_id !== user?.id && 
        a.owner_id !== null
    );

    const activeLedger = (ledgers || []).find((l) => l.id === activeLedgerId);
    const prefs = activeLedger?.my_preferences;

    const hasAiToken = !!localStorage.getItem('byok_llm_key');
    const aiTargetId = localStorage.getItem(`ai_target_account_${activeLedgerId}`);

    const renderAccountCard = (acc: Account) => {
        const canEdit = acc.type !== 'split_clearing' && (acc.owner_id === user?.id || acc.owner_id === null);

        const isDefaultPayment = acc.id === prefs?.default_payment_account_id;
        const isDefaultExpense = acc.id === prefs?.default_expense_account_id;
        const isAiTarget = hasAiToken && String(acc.id) === aiTargetId;

        return (
            <Card key={acc.id} className="relative overflow-hidden">
                <CardHeader className="pb-2">
                    <div className="flex items-start justify-between gap-2">
                        <CardTitle className="flex items-center gap-2 text-base font-semibold text-foreground">
                            {ACCOUNT_TYPE_ICONS[acc.type] || ACCOUNT_TYPE_ICONS.user_funding}
                            <span className="truncate pr-2">{acc.name}</span>
                        </CardTitle>
                        <div className="flex shrink-0 items-center gap-2">
                            {acc.owner_is_active === false && (
                                <Badge variant="secondary" className="gap-1 text-[10px] text-muted-foreground">
                                    <UserXIcon className="size-3" /> Deactivated Member
                                </Badge>
                            )}
                            {isDefaultPayment && <Badge variant="secondary" className="text-[10px]">Default Payment</Badge>}
                            {isDefaultExpense && <Badge variant="secondary" className="text-[10px]">Default Expense</Badge>}
                            {isAiTarget && <Badge variant="warning" className="text-[10px]">AI Target</Badge>}
                            <Badge variant="outline" className="capitalize text-[10px]">
                                {acc.type.replace('_', ' ')}
                            </Badge>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="icon" aria-label={`Account actions for ${acc.name}`}>
                                        <MoreHorizontalIcon />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem onClick={() => navigate(`/transactions?account=${acc.id}`)}>
                                        <ArrowLeftRightIcon className="mr-2 size-4" />
                                        View Transactions
                                    </DropdownMenuItem>
                                    {canEdit && (
                                        <>
                                            <DropdownMenuItem onClick={() => handleEdit(acc)}>
                                                <PencilIcon className="mr-2 size-4" />
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                className="text-destructive focus:bg-destructive focus:text-destructive-foreground"
                                                onClick={() => setDeletingId(acc.id)}
                                            >
                                                <TrashIcon className="mr-2 size-4" />
                                                Delete
                                            </DropdownMenuItem>
                                        </>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </CardHeader>
            <CardContent className="pt-4">
                <p className="text-xs text-muted-foreground">Account Balance</p>
                <div className="text-2xl font-bold font-mono text-foreground mt-1">
                    {centsToCurrency(acc.balance, currencySymbol)}
                </div>
                <div className="mt-3 text-[11px] text-muted-foreground font-mono">
                    ID: #{acc.id}
                </div>
            </CardContent>
        </Card>
        );
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <WalletIcon className="size-6 text-primary" />
                        Space Accounts
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Manage joint pool accounts, personal checking, credit cards, and cash stores.
                    </p>
                </div>
                <Button onClick={() => setIsAddOpen(true)} className="gap-2 shrink-0">
                    <PlusIcon className="size-4" />
                    Add Account
                </Button>
            </div>

            {isPending ? (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Skeleton className="h-36 rounded-xl" />
                    <Skeleton className="h-36 rounded-xl" />
                    <Skeleton className="h-36 rounded-xl" />
                </div>
            ) : accounts.length === 0 ? (
                <Empty className="border border-dashed">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <WalletIcon />
                        </EmptyMedia>
                        <EmptyTitle>No accounts registered yet</EmptyTitle>
                        <EmptyDescription>
                            Click &quot;Add Account&quot; to register a payment source.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <div className="space-y-8">
                    {myAccounts.length > 0 && (
                        <section>
                            <h2 className="text-lg font-semibold mb-3">My Personal Accounts</h2>
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {myAccounts.map(renderAccountCard)}
                            </div>
                        </section>
                    )}
                    {poolAssets.length > 0 && (
                        <section>
                            <h2 className="text-lg font-semibold mb-3">Joint Pool Assets</h2>
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {poolAssets.map(renderAccountCard)}
                            </div>
                        </section>
                    )}
                    {spaceExpenses.length > 0 && (
                        <section>
                            <h2 className="text-lg font-semibold mb-3">Space Expenses</h2>
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {spaceExpenses.map(renderAccountCard)}
                            </div>
                        </section>
                    )}
                    {hasAnyOtherMemberAccounts && (
                        <section>
                            <div className="flex items-center justify-between mb-3">
                                <h2 className="text-lg font-semibold">Other Members' Accounts</h2>
                                <div className="flex items-center space-x-2">
                                    <Switch
                                        id="show-deactivated"
                                        checked={showDeactivatedAccounts}
                                        onCheckedChange={setShowDeactivatedAccounts}
                                    />
                                    <Label htmlFor="show-deactivated" className="text-sm font-normal text-muted-foreground cursor-pointer">
                                        Show deactivated members
                                    </Label>
                                </div>
                            </div>
                            {otherUsersAccounts.length > 0 ? (
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    {otherUsersAccounts.map(renderAccountCard)}
                                </div>
                            ) : (
                                <div className="text-sm text-muted-foreground p-4 border rounded-lg border-dashed text-center">
                                    No active accounts found for other members.
                                </div>
                            )}
                        </section>
                    )}
                    {systemAccounts.length > 0 && (
                        <section>
                            <h2 className="text-lg font-semibold mb-3">System / Clearing</h2>
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {systemAccounts.map(renderAccountCard)}
                            </div>
                        </section>
                    )}
                </div>
            )}

            {/* Modal */}
            <Dialog open={isAddOpen} onOpenChange={(open) => {
                setIsAddOpen(open);
                if (!open) resetForm();
            }}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editingId ? 'Edit Space Account' : 'Add Space Account'}</DialogTitle>
                    </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">
                            Account Name
                        </label>
                        <Input
                            type="text"
                            placeholder="e.g. Joint Revolut Pool, Bob Checking"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            required
                            className="h-10 w-full"
                        />
                    </div>

                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">
                            Account Type
                        </label>
                        <Select value={type} onValueChange={(val) => setType(val as Account['type'])} disabled={!!editingId}>
                            <SelectTrigger className="h-10 w-full bg-background">
                                <SelectValue placeholder="Select account type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="user_funding">Personal / Funding Account</SelectItem>
                                    <SelectItem value="pool_asset">Joint Pool</SelectItem>
                                    <SelectItem value="space_expense">Space Category (Expense)</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">
                            {editingId ? 'Current Funds' : 'Starting Balance'}
                        </label>
                        <CurrencyInput
                            value={initialBalance}
                            onCentsChange={(cents) => setInitialBalance(cents || 0)}
                            currencySymbol={currencySymbol}
                        />
                    </div>

                    {saveMutation.isError && (
                        <p className="text-xs text-destructive">
                            {(saveMutation.error as Error)?.message || 'Failed to save account.'}
                        </p>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={resetForm}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={saveMutation.isPending || !name.trim()}>
                            {editingId ? 'Save Changes' : 'Create Account'}
                        </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog open={deletingId !== null} onOpenChange={(open) => !open && setDeletingId(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete account?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete{' '}
                            {accounts.find((a) => a.id === deletingId)?.name ?? 'this account'}? This action cannot be
                            undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deleteMutation.isPending}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            disabled={deleteMutation.isPending || deletingId === null}
                            onClick={() => {
                                if (deletingId !== null) deleteMutation.mutate(deletingId);
                            }}
                        >
                            {deleteMutation.isPending ? 'Deleting…' : 'Delete'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
