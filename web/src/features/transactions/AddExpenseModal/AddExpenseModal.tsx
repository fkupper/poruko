import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { fetchAccounts } from '@/api/accounts';
import { fetchLedgers } from '@/api/ledgers';
import { createTransaction, updateTransaction } from '@/api/transactions';
import type { Transaction } from '@/api/types';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { ReceiptIcon } from 'lucide-react';
import { AccountSelector } from '@/components/ui/account-selector';
import { ExpenseSplitFields } from '@/features/transactions/ExpenseSplitFields/ExpenseSplitFields';
import { useExpenseSplit } from '@/features/transactions/ExpenseSplitFields/useExpenseSplit';

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

    const split = useExpenseSplit({
        open,
        date,
        amountCents,
        resetToken: transaction?.id ?? 'new',
        seed: transaction
            ? { splitRule: transaction.split_rule, participants: transaction.participants }
            : null,
    });

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
            } else {
                setDescription('');
                setAmountCents(null);
                setDate(new Date().toISOString().split('T')[0]);

                const activeLedger = (ledgers || []).find((l) => l.id === activeLedgerId);
                const prefs = activeLedger?.my_preferences;

                setPayerAccountId(prefs?.default_payment_account_id ?? prefs?.main_personal_account_id ?? null);
                setDestinationAccountId(prefs?.default_expense_account_id ?? null);
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

    const mutation = useMutation({
        mutationFn: async () => {
            if (!activeLedgerId) throw new Error('No active space selected.');
            if (!amountCents || amountCents <= 0) throw new Error('Please enter a valid amount.');
            if (!split.splitValid) {
                if (split.splitRule === 'individual') {
                    throw new Error('Select exactly one person for an individual expense.');
                }
                throw new Error('Select at least one participant and enter a weight greater than zero for each.');
            }
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
                split_rule: split.splitRule,
                participants: split.payloadParticipants,
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

                        <ExpenseSplitFields split={split} />
                    </FieldGroup>

                    {mutation.isError && (
                        <p className="text-xs text-destructive">
                            {(mutation.error as Error)?.message || 'Failed to create transaction.'}
                        </p>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={mutation.isPending || (amountCents || 0) <= 0 || !split.splitValid}
                        >
                            {mutation.isPending ? <Spinner data-icon="inline-start" /> : null}
                            Save Expense
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
