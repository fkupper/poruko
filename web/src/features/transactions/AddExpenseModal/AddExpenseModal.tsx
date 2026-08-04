import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Dialog, DialogClose, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { fetchLedgerMembers } from '@/api/members';
import { fetchAccounts } from '@/api/accounts';
import { createTransaction } from '@/api/transactions';
import type { ParticipantShare, SplitRule } from '@/api/types';
import { centsToCurrency, formatPercent } from '@/lib/currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { Loader2Icon, SparklesIcon } from 'lucide-react';

interface AddExpenseModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function AddExpenseModal({ open, onOpenChange }: AddExpenseModalProps) {
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);

    const [description, setDescription] = React.useState('');
    const [amountCents, setAmountCents] = React.useState(0);
    const [date, setDate] = React.useState(() => new Date().toISOString().split('T')[0]);
    const [payerAccountId, setPayerAccountId] = React.useState<number | null>(null);
    const [splitRule, setSplitRule] = React.useState<SplitRule>('proportional');
    const [manualShares, setManualShares] = React.useState<Record<number, number>>({});

    // Fetch accounts for payer selection
    const { data: accounts } = useQuery({
        queryKey: ['accounts', activeLedgerId],
        queryFn: () => fetchAccounts(activeLedgerId!),
        enabled: !!activeLedgerId && open,
    });

    // Auto-select first account if not set
    React.useEffect(() => {
        if (accounts && accounts.length > 0 && payerAccountId === null) {
            setPayerAccountId(accounts[0].id);
        }
    }, [accounts, payerAccountId]);

    // Fetch members & shareable incomes for dynamic proportional calculation
    const { data: members = [], isPending: isLoadingMembers } = useQuery({
        queryKey: ['members', activeLedgerId, date],
        queryFn: () => fetchLedgerMembers(activeLedgerId!, date),
        enabled: !!activeLedgerId && open,
    });

    // Calculate total shareable income
    const totalShareableIncome = React.useMemo(() => {
        return members.reduce((sum, m) => sum + (m.shareable_income || 0), 0);
    }, [members]);

    // Calculate splits per member
    const calculatedParticipants = React.useMemo<ParticipantShare[]>(() => {
        if (!members.length || amountCents <= 0) return [];

        if (splitRule === 'equal') {
            const perPerson = Math.floor(amountCents / members.length);
            const remainder = amountCents - perPerson * members.length;
            return members.map((m, idx) => ({
                user_id: m.id,
                share_amount: idx === 0 ? perPerson + remainder : perPerson,
                share_ratio: 1 / members.length,
            }));
        }

        if (splitRule === 'proportional') {
            if (totalShareableIncome <= 0) {
                const perPerson = Math.floor(amountCents / members.length);
                return members.map((m) => ({
                    user_id: m.id,
                    share_amount: perPerson,
                    share_ratio: 1 / members.length,
                }));
            }

            let sumShares = 0;
            const shares = members.map((m) => {
                const ratio = m.shareable_income / totalShareableIncome;
                const shareAmount = Math.round(amountCents * ratio);
                sumShares += shareAmount;
                return {
                    user_id: m.id,
                    share_amount: shareAmount,
                    share_ratio: ratio,
                };
            });

            // Adjust first member to balance parent total exactly
            const diff = amountCents - sumShares;
            if (diff !== 0 && shares.length > 0) {
                shares[0].share_amount = (shares[0].share_amount || 0) + diff;
            }

            return shares;
        }

        // Individual manual overrides
        return members.map((m) => ({
            user_id: m.id,
            share_amount: manualShares[m.id] ?? 0,
        }));
    }, [members, amountCents, splitRule, totalShareableIncome, manualShares]);

    const mutation = useMutation({
        mutationFn: async () => {
            if (!activeLedgerId) throw new Error('No active space selected.');
            if (!payerAccountId && accounts && accounts.length > 0) {
                throw new Error('Please select a payer account.');
            }

            return createTransaction(activeLedgerId, {
                description: description || 'Expense',
                amount: amountCents,
                date,
                payer_account_id: payerAccountId || accounts?.[0]?.id || 1,
                type: 'manual',
                split_rule: splitRule,
                participants: calculatedParticipants,
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['transactions', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['settlement-preview', activeLedgerId] });
            onOpenChange(false);
            // Reset form
            setDescription('');
            setAmountCents(0);
        },
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        mutation.mutate();
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogHeader>
                <DialogTitle className="flex items-center gap-2">
                    <SparklesIcon className="size-5 text-primary" />
                    Log New Expense
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
                            Amount (€)
                        </label>
                        <CurrencyInput
                            value={amountCents}
                            onCentsChange={setAmountCents}
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

                {/* Payer Account Select */}
                <div>
                    <label className="text-xs font-medium text-muted-foreground block mb-1">
                        Paid From Account
                    </label>
                    {accounts && accounts.length > 0 ? (
                        <select
                            value={payerAccountId ?? ''}
                            onChange={(e) => setPayerAccountId(Number(e.target.value))}
                            className="h-10 w-full rounded-lg border border-input bg-background px-3 py-1 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            {accounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.name} ({centsToCurrency(acc.balance)})
                                </option>
                            ))}
                        </select>
                    ) : (
                        <input
                            type="number"
                            placeholder="Account ID (Default 1)"
                            value={payerAccountId ?? 1}
                            onChange={(e) => setPayerAccountId(Number(e.target.value))}
                            className="h-10 w-full rounded-lg border border-input bg-transparent px-3 py-1 text-sm"
                        />
                    )}
                </div>

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
                    ) : members.length === 0 ? (
                        <p className="text-xs text-muted-foreground text-center py-2">
                            No members found in this Space.
                        </p>
                    ) : (
                        <div className="space-y-2 text-xs">
                            {members.map((member) => {
                                const participant = calculatedParticipants.find((p) => p.user_id === member.id);
                                const shareAmt = participant?.share_amount || 0;
                                const ratio = participant?.share_ratio || 0;

                                return (
                                    <div key={member.id} className="flex items-center justify-between py-1 border-b border-border/40 last:border-0">
                                        <div>
                                            <span className="font-medium text-foreground">{member.name}</span>
                                            {splitRule === 'proportional' && (
                                                <span className="text-[11px] text-muted-foreground ml-2">
                                                    (Income: {centsToCurrency(member.shareable_income)} · {formatPercent(ratio)})
                                                </span>
                                            )}
                                        </div>
                                        {splitRule === 'individual' ? (
                                            <CurrencyInput
                                                value={manualShares[member.id] || 0}
                                                onCentsChange={(cents) => setManualShares((prev) => ({ ...prev, [member.id]: cents }))}
                                                className="w-28 h-7 text-xs"
                                            />
                                        ) : (
                                            <span className="font-mono font-semibold text-foreground">
                                                {centsToCurrency(shareAmt)}
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
                    <Button type="submit" disabled={mutation.isPending || amountCents <= 0}>
                        {mutation.isPending ? <Loader2Icon className="size-4 animate-spin mr-2" /> : null}
                        Save Expense
                    </Button>
                </DialogFooter>
            </form>
            <DialogClose onClose={() => onOpenChange(false)} />
        </Dialog>
    );
}
