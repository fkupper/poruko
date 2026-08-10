import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { fetchLedgerMembers } from '@/api/members';
import { fetchAccounts } from '@/api/accounts';
import { fetchLedgers } from '@/api/ledgers';
import { createTransaction, updateTransaction } from '@/api/transactions';
import type { ParticipantShare, SplitRule, Transaction } from '@/api/types';
import { centsToCurrency, formatPercent } from '@/lib/currency';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { Loader2Icon, ReceiptIcon } from 'lucide-react';
import { AccountSelector } from '@/components/ui/account-selector';

interface AddExpenseModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transaction?: Transaction | null;
}

export function AddExpenseModal({ open, onOpenChange, transaction }: AddExpenseModalProps) {
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    const currencySymbol = useLedgerCurrencySymbol();

    const [description, setDescription] = React.useState('');
    const [amountCents, setAmountCents] = React.useState<number | null>(null);
    const [date, setDate] = React.useState(() => new Date().toISOString().split('T')[0]);
    const [payerAccountId, setPayerAccountId] = React.useState<number | null>(null);
    const [destinationAccountId, setDestinationAccountId] = React.useState<number | null>(null);
    const [splitRule, setSplitRule] = React.useState<SplitRule>('proportional');
    const [manualShares, setManualShares] = React.useState<Record<number, number>>({});

    const { data: ledgers } = useQuery({ queryKey: ['ledgers'], queryFn: fetchLedgers });

    React.useEffect(() => {
        if (open) {
            if (transaction) {
                // eslint-disable-next-line react-hooks/set-state-in-effect
                setDescription(transaction.description || '');
                 
                setAmountCents(transaction.amount);
                 
                setDate(transaction.date);
                 
                setPayerAccountId(transaction.payer_account_id ?? null);
                 
                setDestinationAccountId(transaction.destination_account_id ?? null);
                 
                setSplitRule(transaction.split_rule);
                
                const mShares: Record<number, number> = {};
                if (transaction.participants) {
                    transaction.participants.forEach((p) => {
                        mShares[p.user_id] = p.share ?? 0;
                    });
                }
                 
                setManualShares(mShares);
            } else {
                 
                setDescription('');
                 
                setAmountCents(null);
                 
                setDate(new Date().toISOString().split('T')[0]);
                
                const activeLedger = (ledgers || []).find(l => l.id === activeLedgerId);
                const prefs = activeLedger?.my_preferences;
                
                 
                setPayerAccountId(prefs?.default_payment_account_id ?? prefs?.main_personal_account_id ?? null);
                 
                setDestinationAccountId(prefs?.default_expense_account_id ?? null);
                
                 
                setSplitRule('proportional');
                 
                setManualShares({});
            }
        }
    }, [open, transaction, ledgers, activeLedgerId]);

    // Fetch accounts for payer selection
    const { data: accounts } = useQuery({
        queryKey: ['accounts', activeLedgerId],
        queryFn: () => fetchAccounts(activeLedgerId!),
        enabled: !!activeLedgerId && open,
    });

    // Auto-select first account if not set
    React.useEffect(() => {
        if (accounts && accounts.length > 0) {
            if (payerAccountId === null) {
                setPayerAccountId(accounts[0].id);
            }
            if (destinationAccountId === null) {
                const spaceExpense = accounts.find(a => a.type === 'space_expense');
                setDestinationAccountId(spaceExpense?.id ?? accounts[0].id);
            }
        }
    }, [accounts, payerAccountId, destinationAccountId]);

    // Fetch members & shareable incomes for dynamic proportional calculation
    const { data: members = [], isPending: isLoadingMembers } = useQuery({
        queryKey: ['members', activeLedgerId, date],
        queryFn: () => fetchLedgerMembers(activeLedgerId!, date),
        enabled: !!activeLedgerId && open,
    });

    const activeMembers = React.useMemo(
        () => members.filter((m) => m.is_active !== false),
        [members],
    );

    // Calculate total shareable income
    const totalShareableIncome = React.useMemo(() => {
        return activeMembers.reduce((sum, m) => sum + (m.shareable_income || 0), 0);
    }, [activeMembers]);

    // Calculate splits per member
    const calculatedParticipants = React.useMemo<ParticipantShare[]>(() => {
        const amt = amountCents || 0;
        if (!activeMembers.length || amt <= 0) return [];

        if (splitRule === 'equal') {
            const perPerson = Math.floor(amt / activeMembers.length);
            const remainder = amt - perPerson * activeMembers.length;
            const equalRatio = 1 / activeMembers.length;
            return activeMembers.map((m, idx) => ({
                user_id: m.id,
                share: perPerson + (idx === 0 ? remainder : 0),
                share_ratio: equalRatio,
            }));
        }

        if (splitRule === 'proportional') {
            if (totalShareableIncome <= 0) {
                // Fallback to equal split if no shareable income set
                const perPerson = Math.floor(amt / activeMembers.length);
                const remainder = amt - perPerson * activeMembers.length;
                const equalRatio = 1 / activeMembers.length;
                return activeMembers.map((m, idx) => ({
                    user_id: m.id,
                    share: perPerson + (idx === 0 ? remainder : 0),
                    share_ratio: equalRatio,
                }));
            }

            let sumShares = 0;
            const shares = activeMembers.map((m, idx) => {
                if (idx === activeMembers.length - 1) {
                    return amt - sumShares;
                }
                const ratio = m.shareable_income / totalShareableIncome;
                const share = Math.round(amt * ratio);
                sumShares += share;
                return share;
            });

            return activeMembers.map((m, idx) => ({
                user_id: m.id,
                share: shares[idx],
                share_ratio: m.shareable_income / totalShareableIncome,
            }));
        }

        // Manual / individual split rule
        return activeMembers.map((m) => ({
            user_id: m.id,
            share: manualShares[m.id] ?? 0,
        }));
    }, [activeMembers, amountCents, splitRule, totalShareableIncome, manualShares]);

    const mutation = useMutation({
        mutationFn: async () => {
            if (!activeLedgerId) throw new Error('No active space selected.');
            if (!amountCents || amountCents <= 0) throw new Error('Please enter a valid amount.');
            const selectedPayerId = payerAccountId || (accounts?.find(a => a.type !== 'space_expense')?.id);
            const selectedDestinationId = destinationAccountId || (accounts?.find(a => a.type === 'space_expense')?.id);
            if (!selectedPayerId) {
                throw new Error('Please select a payer account.');
            }
            if (!selectedDestinationId) {
                throw new Error('Please select a destination account.');
            }

            const payload = {
                description: description.trim() || 'Expense',
                amount: amountCents,
                date,
                payer_account_id: selectedPayerId,
                destination_account_id: selectedDestinationId,
                type: 'manual' as const,
                split_rule: splitRule,
                participants: calculatedParticipants,
            };

            if (transaction) {
                return updateTransaction(activeLedgerId, transaction.id, payload);
            }
            return createTransaction(activeLedgerId, payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['transactions', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['settlement-preview', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
            onOpenChange(false);
            // Reset form
            setDescription('');
            setAmountCents(null);
        },
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        mutation.mutate();
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                    <ReceiptIcon className="size-5 text-primary" />
                    {transaction ? 'Edit Expense' : 'Log New Expense'}
                </DialogTitle>
            </DialogHeader>

            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Description */}
                <div>
                    <label className="text-xs font-medium text-muted-foreground block mb-1">
                        Description
                    </label>
                    <input
                        type="text"
                        placeholder="e.g. Weekly Groceries, Pizza"
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        required
                        className="h-10 w-full rounded-lg border border-input bg-transparent px-3 py-1 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    />
                </div>

                {/* Amount & Date */}
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">
                            Amount
                        </label>
                        <CurrencyInput
                            value={amountCents}
                            onCentsChange={setAmountCents}
                            currencySymbol={currencySymbol}
                            required
                        />
                    </div>
                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">
                            Date
                        </label>
                        <input
                            type="date"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                            required
                            className="h-10 w-full rounded-lg border border-input bg-transparent px-3 py-1 font-mono text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />
                    </div>
                </div>

                
                <AccountSelector
                    label="Paid From Account"
                    usage="payer"
                    accounts={accounts}
                    value={payerAccountId}
                    onChange={setPayerAccountId}
                />
                
                <AccountSelector
                    label="Category (Destination)"
                    usage="destination"
                    accounts={accounts}
                    value={destinationAccountId}
                    onChange={setDestinationAccountId}
                />


                {/* Split Rule Selector */}
                <div>
                    <label className="text-xs font-medium text-muted-foreground block mb-1.5">
                        Split Rule
                    </label>
                    <div className="grid grid-cols-3 gap-2">
                        {(['proportional', 'equal', 'individual'] as SplitRule[]).map((rule) => (
                            <button
                                key={rule}
                                type="button"
                                onClick={() => setSplitRule(rule)}
                                className={`h-9 rounded-lg border text-xs font-medium capitalize transition-all ${
                                    splitRule === rule
                                        ? 'bg-primary text-primary-foreground border-primary shadow-xs'
                                        : 'bg-muted/40 text-muted-foreground hover:bg-muted'
                                }`}
                            >
                                {rule}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Real-time Split Breakdown */}
                <div className="rounded-lg border bg-muted/30 p-3 space-y-2">
                    <div className="flex items-center justify-between text-xs font-semibold text-muted-foreground">
                        <span>Participant Split Breakdown</span>
                        <Badge variant="outline" className="capitalize text-[10px]">
                            {splitRule}
                        </Badge>
                    </div>

                    {isLoadingMembers ? (
                        <div className="flex items-center justify-center p-4">
                            <Loader2Icon className="size-4 animate-spin text-muted-foreground" />
                        </div>
                    ) : activeMembers.length === 0 ? (
                        <p className="text-xs text-muted-foreground text-center py-2">
                            No members found in this Space.
                        </p>
                    ) : (
                        <div className="space-y-2 text-xs">
                            {activeMembers.map((member) => {
                                const participant = calculatedParticipants.find((p) => p.user_id === member.id);
                                const shareAmt = participant?.share || 0;
                                const ratio = participant?.share_ratio || 0;

                                return (
                                    <div key={member.id} className="flex items-center justify-between py-1 border-b border-border/40 last:border-0">
                                        <div>
                                            <span className="font-medium text-foreground">{member.name}</span>
                                            {splitRule === 'proportional' && (
                                                <span className="text-[11px] text-muted-foreground ml-2">
                                                    (Income: {centsToCurrency(member.shareable_income, currencySymbol)} · {formatPercent(ratio)})
                                                </span>
                                            )}
                                        </div>
                                        {splitRule === 'individual' ? (
                                            <CurrencyInput
                                                value={manualShares[member.id] || 0}
                                                onCentsChange={(cents) => setManualShares((prev) => ({ ...prev, [member.id]: cents || 0 }))}
                                                currencySymbol={currencySymbol}
                                                className="w-28 h-7 text-xs"
                                            />
                                        ) : (
                                            <span className="font-mono font-semibold text-foreground">
                                                {centsToCurrency(shareAmt, currencySymbol)}
                                            </span>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {mutation.isError && (
                    <p className="text-xs text-destructive">
                        {(mutation.error as Error)?.message || 'Failed to create transaction.'}
                    </p>
                )}

                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={mutation.isPending || (amountCents || 0) <= 0}>
                        {mutation.isPending ? <Loader2Icon className="size-4 animate-spin mr-2" /> : null}
                        Save Expense
                    </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
