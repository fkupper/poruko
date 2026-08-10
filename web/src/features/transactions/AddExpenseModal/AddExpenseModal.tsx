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
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { fetchLedgerMembers } from '@/api/members';
import { fetchAccounts } from '@/api/accounts';
import { fetchLedgers } from '@/api/ledgers';
import { createTransaction, updateTransaction } from '@/api/transactions';
import type { ParticipantShare, SplitRule, Transaction } from '@/api/types';
import { centsToCurrency, formatPercent } from '@/lib/currency';
import { allocateEqualCents, allocateProportionalCents } from '@/lib/split';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { ReceiptIcon } from 'lucide-react';
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

                const activeLedger = (ledgers || []).find((l) => l.id === activeLedgerId);
                const prefs = activeLedger?.my_preferences;

                setPayerAccountId(prefs?.default_payment_account_id ?? prefs?.main_personal_account_id ?? null);
                setDestinationAccountId(prefs?.default_expense_account_id ?? null);
                setSplitRule('proportional');
                setManualShares({});
            }
        }
    }, [open, transaction, ledgers, activeLedgerId]);

    const { data: accounts } = useQuery({
        queryKey: ['accounts', activeLedgerId],
        queryFn: () => fetchAccounts(activeLedgerId!),
        enabled: !!activeLedgerId && open,
    });

    React.useEffect(() => {
        if (accounts && accounts.length > 0) {
            if (payerAccountId === null) {
                // eslint-disable-next-line react-hooks/set-state-in-effect -- seed defaults once accounts load
                setPayerAccountId(accounts[0].id);
            }
            if (destinationAccountId === null) {
                const spaceExpense = accounts.find((a) => a.type === 'space_expense');
                setDestinationAccountId(spaceExpense?.id ?? accounts[0].id);
            }
        }
    }, [accounts, payerAccountId, destinationAccountId]);

    const { data: members = [], isPending: isLoadingMembers } = useQuery({
        queryKey: ['members', activeLedgerId, date],
        queryFn: () => fetchLedgerMembers(activeLedgerId!, date),
        enabled: !!activeLedgerId && open,
    });

    const activeMembers = React.useMemo(
        () => members.filter((m) => m.is_active !== false),
        [members],
    );

    const totalShareableIncome = React.useMemo(
        () => activeMembers.reduce((sum, m) => sum + (m.shareable_income || 0), 0),
        [activeMembers],
    );

    const calculatedParticipants = React.useMemo<ParticipantShare[]>(() => {
        const amt = amountCents || 0;
        if (!activeMembers.length || amt <= 0) return [];

        const participantIds = activeMembers.map((m) => m.id);

        if (splitRule === 'equal') {
            const shares = allocateEqualCents(amt, participantIds);
            const equalRatio = 1 / activeMembers.length;
            return activeMembers.map((m) => ({
                user_id: m.id,
                share: shares[m.id] ?? 0,
                share_ratio: equalRatio,
            }));
        }

        if (splitRule === 'proportional') {
            const shareableByUser: Record<number, number> = {};
            activeMembers.forEach((m) => {
                shareableByUser[m.id] = m.shareable_income || 0;
            });
            const shares = allocateProportionalCents(amt, participantIds, shareableByUser);
            return activeMembers.map((m) => ({
                user_id: m.id,
                share: shares[m.id] ?? 0,
                share_ratio:
                    totalShareableIncome > 0
                        ? (m.shareable_income || 0) / totalShareableIncome
                        : 1 / activeMembers.length,
            }));
        }

        return activeMembers.map((m) => ({
            user_id: m.id,
            share: manualShares[m.id] ?? 0,
        }));
    }, [activeMembers, amountCents, splitRule, totalShareableIncome, manualShares]);

    const mutation = useMutation({
        mutationFn: async () => {
            if (!activeLedgerId) throw new Error('No active space selected.');
            if (!amountCents || amountCents <= 0) throw new Error('Please enter a valid amount.');
            const selectedPayerId = payerAccountId || accounts?.find((a) => a.type !== 'space_expense')?.id;
            const selectedDestinationId =
                destinationAccountId || accounts?.find((a) => a.type === 'space_expense')?.id;
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

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="expense-description">Description</FieldLabel>
                            <Input
                                id="expense-description"
                                type="text"
                                placeholder="e.g. Weekly Groceries, Pizza"
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                                required
                            />
                        </Field>

                        <div className="grid grid-cols-2 gap-3">
                            <Field>
                                <FieldLabel htmlFor="expense-amount">Amount</FieldLabel>
                                <CurrencyInput
                                    id="expense-amount"
                                    value={amountCents}
                                    onCentsChange={setAmountCents}
                                    currencySymbol={currencySymbol}
                                    required
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="expense-date">Date</FieldLabel>
                                <Input
                                    id="expense-date"
                                    type="date"
                                    value={date}
                                    onChange={(e) => setDate(e.target.value)}
                                    required
                                    className="font-mono"
                                />
                            </Field>
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

                        <Field>
                            <FieldLabel>Split Rule</FieldLabel>
                            <ToggleGroup
                                type="single"
                                value={splitRule}
                                onValueChange={(value) => {
                                    if (value) setSplitRule(value as SplitRule);
                                }}
                                variant="outline"
                                className="w-full"
                            >
                                {(['proportional', 'equal', 'individual'] as SplitRule[]).map((rule) => (
                                    <ToggleGroupItem key={rule} value={rule} className="flex-1 capitalize">
                                        {rule}
                                    </ToggleGroupItem>
                                ))}
                            </ToggleGroup>
                        </Field>
                    </FieldGroup>

                    <div className="flex flex-col gap-2 rounded-lg border bg-muted/30 p-3">
                        <div className="flex items-center justify-between text-xs font-semibold text-muted-foreground">
                            <span>Participant Split Breakdown</span>
                            <Badge variant="outline" className="capitalize text-[10px]">
                                {splitRule}
                            </Badge>
                        </div>

                        {isLoadingMembers ? (
                            <div className="flex items-center justify-center p-4">
                                <Spinner className="text-muted-foreground" />
                            </div>
                        ) : activeMembers.length === 0 ? (
                            <p className="py-2 text-center text-xs text-muted-foreground">
                                No members found in this Space.
                            </p>
                        ) : (
                            <div className="flex flex-col gap-2 text-xs">
                                {activeMembers.map((member) => {
                                    const participant = calculatedParticipants.find((p) => p.user_id === member.id);
                                    const shareAmt = participant?.share || 0;
                                    const ratio =
                                        totalShareableIncome > 0
                                            ? member.shareable_income / totalShareableIncome
                                            : activeMembers.length > 0
                                              ? 1 / activeMembers.length
                                              : 0;

                                    return (
                                        <div
                                            key={member.id}
                                            className="flex items-center justify-between border-b border-border/40 py-1 last:border-0"
                                        >
                                            <div>
                                                <span className="font-medium text-foreground">{member.name}</span>
                                                {splitRule === 'proportional' && (
                                                    <span className="ml-2 text-[11px] text-muted-foreground">
                                                        (Income: {centsToCurrency(member.shareable_income, currencySymbol)} ·{' '}
                                                        {formatPercent(ratio)})
                                                    </span>
                                                )}
                                            </div>
                                            {splitRule === 'individual' ? (
                                                <CurrencyInput
                                                    value={manualShares[member.id] || 0}
                                                    onCentsChange={(cents) =>
                                                        setManualShares((prev) => ({
                                                            ...prev,
                                                            [member.id]: cents || 0,
                                                        }))
                                                    }
                                                    currencySymbol={currencySymbol}
                                                    className="w-28"
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
                            {mutation.isPending ? <Spinner data-icon="inline-start" /> : null}
                            Save Expense
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
