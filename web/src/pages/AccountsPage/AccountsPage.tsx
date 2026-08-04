import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchAccounts, createAccount } from '@/api/accounts';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { PlusIcon, WalletIcon, LandmarkIcon, CreditCardIcon, BanknoteIcon } from 'lucide-react';
import type { Account } from '@/api/types';

const ACCOUNT_TYPE_ICONS: Record<Account['type'], React.ReactNode> = {
    joint_pool: <LandmarkIcon className="size-5 text-emerald-500" />,
    personal: <WalletIcon className="size-5 text-blue-500" />,
    cash: <BanknoteIcon className="size-5 text-amber-500" />,
    credit: <CreditCardIcon className="size-5 text-purple-500" />,
};

export default function AccountsPage() {
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);

    const [isAddOpen, setIsAddOpen] = React.useState(false);
    const [name, setName] = React.useState('');
    const [type, setType] = React.useState<Account['type']>('personal');
    const [initialBalance, setInitialBalance] = React.useState(0);

    const { data: accounts = [], isPending } = useQuery({
        queryKey: ['accounts', activeLedgerId],
        queryFn: () => fetchAccounts(activeLedgerId!),
        enabled: !!activeLedgerId,
    });

    const mutation = useMutation({
        mutationFn: async () => {
            if (!activeLedgerId) throw new Error('No active space.');
            return createAccount(activeLedgerId, {
                name,
                type,
                balance: initialBalance,
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
            setIsAddOpen(false);
            setName('');
            setInitialBalance(0);
        },
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        mutation.mutate();
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
                <div className="h-48 rounded-xl border bg-muted/20 animate-pulse flex items-center justify-center text-sm text-muted-foreground">
                    Loading space accounts...
                </div>
            ) : accounts.length === 0 ? (
                <div className="rounded-xl border border-dashed p-12 text-center text-muted-foreground text-sm">
                    No accounts registered yet. Click "Add Account" to register a payment source.
                </div>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {accounts.map((acc) => (
                        <Card key={acc.id} className="relative overflow-hidden">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-base font-semibold text-foreground flex items-center gap-2">
                                    {ACCOUNT_TYPE_ICONS[acc.type] || ACCOUNT_TYPE_ICONS.personal}
                                    {acc.name}
                                </CardTitle>
                                <Badge variant="outline" className="capitalize text-[10px]">
                                    {acc.type.replace('_', ' ')}
                                </Badge>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <p className="text-xs text-muted-foreground">Account Balance</p>
                                <div className="text-2xl font-bold font-mono text-foreground mt-1">
                                    {centsToCurrency(acc.balance)}
                                </div>
                                <div className="mt-3 text-[11px] text-muted-foreground font-mono">
                                    ID: #{acc.id}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {/* Modal */}
            <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                <DialogHeader>
                    <DialogTitle>Add Space Account</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">
                            Account Name
                        </label>
                        <input
                            type="text"
                            placeholder="e.g. Joint Revolut Pool, Bob Checking"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            required
                            className="h-10 w-full rounded-lg border border-input bg-transparent px-3 py-1 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                        />
                    </div>

                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">
                            Account Type
                        </label>
                        <select
                            value={type}
                            onChange={(e) => setType(e.target.value as Account['type'])}
                            className="h-10 w-full rounded-lg border border-input bg-background px-3 py-1 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option value="joint_pool">Joint Pool (Clearinghouse)</option>
                            <option value="personal">Personal Account</option>
                            <option value="credit">Credit Card</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>

                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">
                            Starting Balance (€)
                        </label>
                        <CurrencyInput
                            value={initialBalance}
                            onCentsChange={setInitialBalance}
                        />
                    </div>

                    {mutation.isError && (
                        <p className="text-xs text-destructive">
                            {(mutation.error as Error)?.message || 'Failed to create account.'}
                        </p>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setIsAddOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={mutation.isPending || !name.trim()}>
                            Create Account
                        </Button>
                    </DialogFooter>
                </form>
                <DialogClose onClose={() => setIsAddOpen(false)} />
            </Dialog>
        </div>
    );
}
