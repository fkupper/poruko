import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CheckCircle2Icon, XCircleIcon } from 'lucide-react';

import {
    approvePendingTransaction,
    approvePendingTransactions,
    fetchPendingTransactions,
    rejectPendingTransaction,
    rejectPendingTransactions,
} from '@/api/pending-transactions';
import type { PendingTransaction } from '@/api/types';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerStore } from '@/stores/ledgerStore';

interface ReviewRequest {
    decision: 'approve' | 'reject';
    ids: number[];
}

function isReadyForApproval(transaction: PendingTransaction): boolean {
    return transaction.payer_account_id !== null
        && transaction.destination_account_id !== null
        && transaction.suggested_amount !== null
        && transaction.suggested_split_rule !== null
        && transaction.date !== null;
}

export function PendingApprovalTable() {
    const activeLedgerId = useLedgerStore((state) => state.activeLedgerId);
    const currencySymbol = useLedgerCurrencySymbol();
    const queryClient = useQueryClient();
    const [selectedIds, setSelectedIds] = React.useState<Set<number>>(new Set());
    const [rejectingIds, setRejectingIds] = React.useState<number[]>([]);

    const { data: pendingTransactions = [], isPending } = useQuery({
        queryKey: ['pending-transactions', activeLedgerId],
        queryFn: () => fetchPendingTransactions(activeLedgerId!),
        enabled: activeLedgerId !== null,
    });

    const reviewMutation = useMutation({
        mutationFn: async ({ decision, ids }: ReviewRequest) => {
            if (activeLedgerId === null) {
                throw new Error('No active ledger.');
            }

            if (decision === 'approve') {
                return ids.length === 1
                    ? approvePendingTransaction(activeLedgerId, ids[0])
                    : approvePendingTransactions(activeLedgerId, ids);
            }

            return ids.length === 1
                ? rejectPendingTransaction(activeLedgerId, ids[0])
                : rejectPendingTransactions(activeLedgerId, ids);
        },
        onSuccess: () => {
            setSelectedIds(new Set());
            setRejectingIds([]);
            queryClient.invalidateQueries({ queryKey: ['pending-transactions', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['transactions', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['settlement-preview', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
        },
    });

    const selectedTransactions = pendingTransactions.filter((transaction) => selectedIds.has(transaction.id));
    const selectedAreReady = selectedTransactions.length > 0
        && selectedTransactions.every(isReadyForApproval);
    const allSelected = pendingTransactions.length > 0
        && selectedIds.size === pendingTransactions.length;

    const toggleTransaction = (transactionId: number, checked: boolean) => {
        setSelectedIds((current) => {
            const next = new Set(current);

            if (checked) {
                next.add(transactionId);
            } else {
                next.delete(transactionId);
            }

            return next;
        });
    };

    if (isPending) {
        return (
            <div className="flex flex-col gap-3 rounded-xl border p-4">
                <Skeleton className="h-8 w-56" />
                <Skeleton className="h-28 w-full" />
            </div>
        );
    }

    if (pendingTransactions.length === 0) {
        return null;
    }

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>Pending transaction approvals</CardTitle>
                    <CardDescription>
                        Review proposals before they create postings or affect settlement balances.
                    </CardDescription>
                    <CardAction className="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            disabled={!selectedAreReady || reviewMutation.isPending}
                            onClick={() => reviewMutation.mutate({
                                decision: 'approve',
                                ids: Array.from(selectedIds),
                            })}
                        >
                            <CheckCircle2Icon data-icon="inline-start" />
                            Approve selected
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={selectedIds.size === 0 || reviewMutation.isPending}
                            onClick={() => setRejectingIds(Array.from(selectedIds))}
                        >
                            <XCircleIcon data-icon="inline-start" />
                            Reject selected
                        </Button>
                    </CardAction>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10">
                                    <Checkbox
                                        aria-label="Select all pending transactions"
                                        checked={allSelected || (selectedIds.size > 0 && 'indeterminate')}
                                        onCheckedChange={(checked) => {
                                            setSelectedIds(
                                                checked === true
                                                    ? new Set(pendingTransactions.map((transaction) => transaction.id))
                                                    : new Set(),
                                            );
                                        }}
                                    />
                                </TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>Source</TableHead>
                                <TableHead>Accounts</TableHead>
                                <TableHead className="text-right">Amount</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {pendingTransactions.map((transaction) => {
                                const ready = isReadyForApproval(transaction);

                                return (
                                    <TableRow key={transaction.id}>
                                        <TableCell>
                                            <Checkbox
                                                aria-label={`Select ${transaction.suggested_description || `proposal ${transaction.id}`}`}
                                                checked={selectedIds.has(transaction.id)}
                                                onCheckedChange={(checked) => {
                                                    toggleTransaction(transaction.id, checked === true);
                                                }}
                                            />
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {transaction.date || '—'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-col gap-1">
                                                <span className="font-medium">
                                                    {transaction.suggested_description || transaction.raw_description || 'Untitled proposal'}
                                                </span>
                                                {!ready && (
                                                    <Badge variant="outline">Needs details</Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary" className="capitalize">
                                                {transaction.source.replaceAll('_', ' ')}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {transaction.payer_account_name || 'Unassigned'}
                                            {' → '}
                                            {transaction.destination_account_name || 'Unassigned'}
                                        </TableCell>
                                        <TableCell className="text-right font-mono font-semibold">
                                            {transaction.suggested_amount === null
                                                ? '—'
                                                : centsToCurrency(transaction.suggested_amount, currencySymbol)}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    size="icon-sm"
                                                    variant="ghost"
                                                    aria-label={`Approve ${transaction.suggested_description || `proposal ${transaction.id}`}`}
                                                    disabled={!ready || reviewMutation.isPending}
                                                    onClick={() => reviewMutation.mutate({
                                                        decision: 'approve',
                                                        ids: [transaction.id],
                                                    })}
                                                >
                                                    <CheckCircle2Icon />
                                                </Button>
                                                <Button
                                                    size="icon-sm"
                                                    variant="ghost"
                                                    aria-label={`Reject ${transaction.suggested_description || `proposal ${transaction.id}`}`}
                                                    disabled={reviewMutation.isPending}
                                                    onClick={() => setRejectingIds([transaction.id])}
                                                >
                                                    <XCircleIcon />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>

                    {reviewMutation.isError && (
                        <p className="mt-3 text-sm text-destructive">
                            {(reviewMutation.error as Error).message || 'The review could not be completed.'}
                        </p>
                    )}
                </CardContent>
            </Card>

            <AlertDialog
                open={rejectingIds.length > 0}
                onOpenChange={(open) => {
                    if (!open) {
                        setRejectingIds([]);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Reject transaction proposals?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Rejected proposals will not create ledger postings or affect settlement balances.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            disabled={reviewMutation.isPending}
                            onClick={() => reviewMutation.mutate({
                                decision: 'reject',
                                ids: rejectingIds,
                            })}
                        >
                            Reject {rejectingIds.length > 1 ? `${rejectingIds.length} proposals` : 'proposal'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
