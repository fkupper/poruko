import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PlusIcon, SaveIcon } from 'lucide-react';

import { createAccount, fetchAccounts } from '@/api/accounts';
import { updateBankAccountMapping } from '@/api/ingestion';
import { updatePendingTransaction } from '@/api/pending-transactions';
import type { PendingTransaction } from '@/api/types';
import { AccountSelector } from '@/components/ui/account-selector';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { ExpenseSplitFields } from '@/features/transactions/ExpenseSplitFields/ExpenseSplitFields';
import { useExpenseSplit } from '@/features/transactions/ExpenseSplitFields/useExpenseSplit';
import { useLedgerStore } from '@/stores/ledgerStore';

interface PendingTransactionReviewDialogProps {
    transaction: PendingTransaction | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

function rawString(transaction: PendingTransaction, key: string): string | null {
    const value = transaction.raw_data[key];
    return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function rawNumber(transaction: PendingTransaction, key: string): number | null {
    const value = transaction.raw_data[key];
    return typeof value === 'number' ? value : null;
}

export function PendingTransactionReviewDialog({
    transaction,
    open,
    onOpenChange,
}: PendingTransactionReviewDialogProps) {
    const activeLedgerId = useLedgerStore((state) => state.activeLedgerId);
    const queryClient = useQueryClient();
    const [payerAccountId, setPayerAccountId] = React.useState<number | null>(null);
    const [destinationAccountId, setDestinationAccountId] = React.useState<number | null>(null);
    const [createFor, setCreateFor] = React.useState<'payer' | 'destination'>('payer');
    const [newAccountName, setNewAccountName] = React.useState('');

    const split = useExpenseSplit({
        open: open && transaction !== null,
        date: transaction?.date,
        amountCents: transaction?.suggested_amount ?? null,
        resetToken: transaction?.id ?? 'review',
        seed: transaction
            ? {
                splitRule: transaction.suggested_split_rule ?? 'proportional',
                participants: transaction.suggested_participants,
            }
            : null,
    });

    const accountsQuery = useQuery({
        queryKey: ['accounts', activeLedgerId],
        queryFn: () => fetchAccounts(activeLedgerId!),
        enabled: open && activeLedgerId !== null,
    });

    React.useEffect(() => {
        if (!transaction || !open) return;

        // eslint-disable-next-line react-hooks/set-state-in-effect -- seed the review form when a proposal opens
        setPayerAccountId(transaction.payer_account_id);
        setDestinationAccountId(transaction.destination_account_id);
        setCreateFor(transaction.payer_account_id === null ? 'payer' : 'destination');
        setNewAccountName(
            transaction.payer_account_id === null
                ? rawString(transaction, 'bank_account_name') ?? 'Imported bank account'
                : 'Imported expenses',
        );
    }, [transaction, open]);

    const saveMutation = useMutation({
        mutationFn: async () => {
            if (!transaction || activeLedgerId === null) throw new Error('No proposal selected.');
            if (!split.splitValid) {
                if (split.splitRule === 'individual') {
                    throw new Error('Select exactly one person for an individual expense.');
                }
                throw new Error('Select at least one participant and enter a weight greater than zero for each.');
            }

            const mappingId = rawNumber(transaction, 'bank_account_mapping_id');
            if (mappingId !== null && payerAccountId !== null) {
                await updateBankAccountMapping(activeLedgerId, mappingId, payerAccountId);
            }

            return updatePendingTransaction(activeLedgerId, transaction.id, {
                payer_account_id: payerAccountId,
                destination_account_id: destinationAccountId,
                split_rule: split.splitRule,
                participants: split.payloadParticipants,
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['pending-transactions', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['ai-import-mappings', activeLedgerId] });
            onOpenChange(false);
        },
    });

    const createMutation = useMutation({
        mutationFn: async () => {
            if (!transaction || activeLedgerId === null) throw new Error('No proposal selected.');

            const ownership = rawString(transaction, 'ownership');
            const account = await createAccount(activeLedgerId, {
                name: newAccountName,
                type: createFor === 'destination'
                    ? 'space_expense'
                    : ownership === 'joint' ? 'pool_asset' : 'user_funding',
            });

            if (createFor === 'payer') {
                setPayerAccountId(account.id);
                const mappingId = rawNumber(transaction, 'bank_account_mapping_id');
                if (mappingId !== null) {
                    await updateBankAccountMapping(activeLedgerId, mappingId, account.id);
                }
            } else {
                setDestinationAccountId(account.id);
            }

            return account;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['ai-import-mappings', activeLedgerId] });
        },
    });

    const suggestedAccountId = transaction ? rawNumber(transaction, 'suggested_account_id') : null;
    const canSave = payerAccountId !== null
        && destinationAccountId !== null
        && split.splitValid
        && !split.isLoadingMembers;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Review transaction</DialogTitle>
                    <DialogDescription>
                        Confirm accounts and sharing type before this proposal can be approved.
                    </DialogDescription>
                </DialogHeader>

                {transaction && (
                    <FieldGroup>
                        <AccountSelector
                            label="Payment account"
                            usage="payer"
                            accounts={accountsQuery.data}
                            value={payerAccountId}
                            onChange={setPayerAccountId}
                            autoSelect={false}
                        />
                        {suggestedAccountId !== null && payerAccountId === null && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setPayerAccountId(suggestedAccountId)}
                            >
                                Use suggested account
                            </Button>
                        )}

                        <AccountSelector
                            label="Expense category"
                            usage="destination"
                            accounts={accountsQuery.data}
                            value={destinationAccountId}
                            onChange={setDestinationAccountId}
                            autoSelect={false}
                        />

                        <ExpenseSplitFields
                            split={split}
                            radioGroupName="review-individual-participant"
                        />

                        <Field>
                            <FieldLabel>Create a Poruko account</FieldLabel>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Select
                                    value={createFor}
                                    onValueChange={(value) => setCreateFor(value as 'payer' | 'destination')}
                                >
                                    <SelectTrigger className="w-full sm:w-40">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="payer">Bank account</SelectItem>
                                            <SelectItem value="destination">Expense category</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <Input
                                    value={newAccountName}
                                    onChange={(event) => setNewAccountName(event.target.value)}
                                    placeholder="Account name"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={newAccountName.trim() === '' || createMutation.isPending}
                                    onClick={() => createMutation.mutate()}
                                >
                                    {createMutation.isPending
                                        ? <Spinner data-icon="inline-start" />
                                        : <PlusIcon data-icon="inline-start" />}
                                    Create
                                </Button>
                            </div>
                            <FieldDescription>
                                New bank accounts respect the AI ownership suggestion; expense categories stay shared.
                            </FieldDescription>
                        </Field>

                        {(saveMutation.isError || createMutation.isError) && (
                            <p className="text-sm text-destructive">
                                {(saveMutation.error ?? createMutation.error)?.message}
                            </p>
                        )}
                    </FieldGroup>
                )}

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
                    <Button
                        disabled={!canSave || saveMutation.isPending}
                        onClick={() => saveMutation.mutate()}
                    >
                        {saveMutation.isPending
                            ? <Spinner data-icon="inline-start" />
                            : <SaveIcon data-icon="inline-start" />}
                        Save review details
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
