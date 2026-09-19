import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import {
    fetchSettlementPreview,
    fetchSettlementPeriods,
    executeSettlement,
    recordMidCycleSettlementTransfer,
} from '@/api/settlements';
import type { SettlementTransferInstruction } from '@/api/types';
import { centsToCurrency, formatPercent } from '@/lib/currency';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { TransactionsTable } from '@/features/transactions/TransactionsTable/TransactionsTable';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import {
    AlertTriangleIcon,
    ArrowRightIcon,
    CheckCircle2Icon,
    ChevronDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ChevronUpIcon,
    HandCoinsIcon,
    LockIcon,
    SendIcon,
} from 'lucide-react';

export default function SettlementPage() {
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    const currencySymbol = useLedgerCurrencySymbol();
    const [searchParams, setSearchParams] = useSearchParams();

    const selectedDate = searchParams.get('date') || undefined;

    const [isConfirmOpen, setIsConfirmOpen] = React.useState(false);
    const [showMathBreakdown, setShowMathBreakdown] = React.useState(true);
    const [confirmText, setConfirmText] = React.useState('');
    const [selectedTransfer, setSelectedTransfer] = React.useState<
        (SettlementTransferInstruction & { idempotencyKey: string }) | null
    >(null);

    const { data: periods = [] } = useQuery({
        queryKey: ['settlement-periods', activeLedgerId],
        queryFn: () => fetchSettlementPeriods(activeLedgerId!),
        enabled: !!activeLedgerId,
    });

    const { data: settlement, isPending, isError, refetch } = useQuery({
        queryKey: ['settlement-preview', activeLedgerId, selectedDate],
        queryFn: () => fetchSettlementPreview(activeLedgerId!, selectedDate),
        enabled: !!activeLedgerId,
    });

    const currentIndex = periods.findIndex((p) => p.period_end === settlement?.period_end);
    const prevPeriod = currentIndex > 0 ? periods[currentIndex - 1] : null;
    const nextPeriod =
        currentIndex >= 0 && currentIndex < periods.length - 1 ? periods[currentIndex + 1] : null;

    const mutation = useMutation({
        mutationFn: async () => {
            if (!activeLedgerId || !settlement) throw new Error('No active settlement preview.');
            await executeSettlement(activeLedgerId, settlement.period_end);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['settlement-preview', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['settlement-periods', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['transactions', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
            setIsConfirmOpen(false);
            setConfirmText('');
        },
    });

    const transferMutation = useMutation({
        mutationFn: async () => {
            if (!activeLedgerId || !settlement || !selectedTransfer) {
                throw new Error('No mid-cycle transfer selected.');
            }

            return recordMidCycleSettlementTransfer(activeLedgerId, {
                period_end: settlement.period_end,
                from_account_id: selectedTransfer.from_account_id,
                to_account_id: selectedTransfer.to_account_id,
                amount: selectedTransfer.amount,
                idempotency_key: selectedTransfer.idempotencyKey,
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['settlement-preview', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['settlement-periods', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['transactions', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
            setSelectedTransfer(null);
        },
    });

    const openTransferDialog = (transfer: SettlementTransferInstruction) => {
        transferMutation.reset();
        setSelectedTransfer({
            ...transfer,
            idempotencyKey: globalThis.crypto.randomUUID(),
        });
    };

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground">
                        <HandCoinsIcon className="size-6 text-inflow" />
                        End-of-Month Settlement Engine
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Calculate true-up contributions, review math breakdowns, and lock period boundaries.
                    </p>
                </div>
                {settlement &&
                    (settlement.is_settled ? (
                        <Button variant="outline" disabled className="gap-2 shrink-0">
                            <LockIcon data-icon="inline-start" className="text-muted-foreground" />
                            Month Locked & Settled
                        </Button>
                    ) : (
                        <Button
                            onClick={() => setIsConfirmOpen(true)}
                            className="gap-2 shrink-0 shadow-xs"
                            variant="default"
                        >
                            <LockIcon data-icon="inline-start" />
                            Execute & Lock Month
                        </Button>
                    ))}
            </div>

            {periods.length > 0 && (
                <div className="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            disabled={!prevPeriod}
                            aria-label="Previous settlement period"
                            onClick={() => prevPeriod && setSearchParams({ date: prevPeriod.period_end })}
                        >
                            <ChevronLeftIcon />
                        </Button>

                        <span className="min-w-[110px] px-2 text-center font-mono text-sm font-semibold">
                            {periods.find((p) => p.period_end === settlement?.period_end)?.label ||
                                settlement?.period_end}
                        </span>

                        <Button
                            variant="outline"
                            size="icon"
                            disabled={!nextPeriod}
                            aria-label="Next settlement period"
                            onClick={() => nextPeriod && setSearchParams({ date: nextPeriod.period_end })}
                        >
                            <ChevronRightIcon />
                        </Button>

                        <Select
                            value={settlement?.period_end || ''}
                            onValueChange={(value) => setSearchParams({ date: value })}
                        >
                            <SelectTrigger aria-label="Settlement period" className="h-9 min-w-[180px]">
                                <SelectValue placeholder="Select period" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {periods.map((p) => (
                                        <SelectItem key={p.period_end} value={p.period_end}>
                                            {p.label} ({p.status.toUpperCase()})
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    {settlement?.is_settled ? (
                        <Badge variant="success" className="gap-1.5 px-3 py-1 text-xs">
                            <LockIcon className="size-3.5" />
                            SETTLED{' '}
                            {settlement.executed_at
                                ? `(${new Date(settlement.executed_at).toLocaleDateString()})`
                                : ''}
                        </Badge>
                    ) : (
                        <Badge variant="warning" className="gap-1.5 px-3 py-1 text-xs">
                            <span className="size-2 animate-pulse rounded-full bg-amber-500" />
                            OPEN CYCLE
                        </Badge>
                    )}
                </div>
            )}

            {isPending ? (
                <div className="flex flex-col gap-3 rounded-xl border p-4">
                    <Skeleton className="h-10 w-full" />
                    <Skeleton className="h-24 w-full" />
                    <Skeleton className="h-32 w-full" />
                </div>
            ) : isError || !settlement ? (
                <Empty className="border border-destructive/20 bg-destructive/10">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <AlertTriangleIcon className="text-destructive" />
                        </EmptyMedia>
                        <EmptyTitle className="text-destructive">Failed to calculate settlement preview.</EmptyTitle>
                        <EmptyDescription>
                            <Button variant="outline" size="sm" onClick={() => void refetch()}>
                                Retry Calculation
                            </Button>
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <>
                    <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-muted/30 p-4 text-xs font-medium">
                        <div className="flex items-center gap-3">
                            <Badge variant="secondary">
                                Period: {settlement.period_start} to {settlement.period_end}
                            </Badge>
                            <span className="capitalize text-muted-foreground">
                                Mode: {settlement.settlement_mode.replace('_', ' ')}
                            </span>
                        </div>
                        <div className="flex items-center gap-4 font-mono">
                            <span>
                                Total Spend:{' '}
                                <strong className="text-foreground">
                                    {centsToCurrency(settlement.summary.total_shared_spend, currencySymbol)}
                                </strong>
                            </span>
                            <span>
                                Pool Balance:{' '}
                                <strong className="text-foreground">
                                    {centsToCurrency(settlement.summary.pool_current_balance, currencySymbol)}
                                </strong>
                            </span>
                        </div>
                    </div>

                    <div className="flex flex-col gap-3">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            Required Transfer Actions
                        </h2>
                        {settlement.required_transfers.length === 0 ? (
                            <Empty className="border bg-card">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <CheckCircle2Icon className="text-inflow" />
                                    </EmptyMedia>
                                    <EmptyTitle>All members are completely balanced</EmptyTitle>
                                    <EmptyDescription>No transfer instructions needed.</EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="grid gap-3">
                                {settlement.required_transfers.map((tx, idx) => (
                                    <Card key={idx} className="border-l-4 border-l-inflow">
                                        <CardHeader>
                                            <div className="flex items-center gap-3">
                                                <div className="flex size-10 items-center justify-center rounded-full bg-inflow/15 text-inflow">
                                                    <ArrowRightIcon className="size-5" />
                                                </div>
                                                <div>
                                                    <CardTitle>{tx.instruction}</CardTitle>
                                                    <CardDescription>
                                                        From Account #{tx.from_account_id} to Joint Account #
                                                        {tx.to_account_id}
                                                    </CardDescription>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                            <div className="flex flex-col gap-2">
                                                {!settlement.is_settled && (
                                                    <Badge variant="secondary" className="w-fit">
                                                        Available as a mid-cycle transfer
                                                    </Badge>
                                                )}
                                                <p className="text-xs text-muted-foreground">
                                                    Recording this now applies it to the cycle ending{' '}
                                                    {settlement.period_end} and reduces the final true-up.
                                                </p>
                                            </div>
                                            <div className="shrink-0 font-mono text-xl font-bold text-inflow">
                                                {centsToCurrency(tx.amount, currencySymbol)}
                                            </div>
                                        </CardContent>
                                        {!settlement.is_settled && (
                                            <CardFooter className="justify-end">
                                                <Button onClick={() => openTransferDialog(tx)}>
                                                    <SendIcon data-icon="inline-start" />
                                                    Record transfer now
                                                </Button>
                                            </CardFooter>
                                        )}
                                    </Card>
                                ))}
                            </div>
                        )}
                    </div>

                    <Card>
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-base font-semibold text-foreground">
                                    Detailed Member Math Breakdown
                                </CardTitle>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setShowMathBreakdown(!showMathBreakdown)}
                                    className="gap-1 text-xs"
                                >
                                    {showMathBreakdown ? (
                                        <>
                                            Hide Breakdown <ChevronUpIcon data-icon="inline-end" />
                                        </>
                                    ) : (
                                        <>
                                            Show Breakdown <ChevronDownIcon data-icon="inline-end" />
                                        </>
                                    )}
                                </Button>
                            </div>
                        </CardHeader>
                        {showMathBreakdown && (
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Member</TableHead>
                                            <TableHead>Income Share Ratio</TableHead>
                                            <TableHead className="text-right">Target Liability</TableHead>
                                            <TableHead className="text-right">Paid Out of Pocket</TableHead>
                                            <TableHead className="text-right">Net Settlement Balance</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {settlement.user_breakdowns.map((user) => (
                                            <TableRow key={user.user_id}>
                                                <TableCell className="font-semibold">{user.name}</TableCell>
                                                <TableCell className="font-mono text-xs">
                                                    {formatPercent(user.active_ratio)}
                                                </TableCell>
                                                <TableCell className="text-right font-mono">
                                                    {centsToCurrency(user.target_liability, currencySymbol)}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-muted-foreground">
                                                    {centsToCurrency(user.paid_out_of_pocket, currencySymbol)}
                                                </TableCell>
                                                <TableCell
                                                    className={`text-right font-mono font-bold ${
                                                        user.net_balance < 0 ? 'text-outflow' : 'text-inflow'
                                                    }`}
                                                >
                                                    {centsToCurrency(user.net_balance, currencySymbol)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        )}
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-base font-semibold text-foreground">
                                Cycle Transactions ({settlement.period_start} to {settlement.period_end})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <TransactionsTable
                                fixedFilters={{
                                    from_date: settlement.period_start,
                                    to_date: settlement.period_end,
                                }}
                                showFilters={false}
                            />
                        </CardContent>
                    </Card>
                </>
            )}

            <Dialog open={isConfirmOpen} onOpenChange={setIsConfirmOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-destructive">
                            <AlertTriangleIcon className="size-5" />
                            Confirm Settlement Cycle Execution
                        </DialogTitle>
                    </DialogHeader>

                    <div className="flex flex-col gap-4 text-sm text-muted-foreground">
                        <p>
                            Executing settlement for period ending{' '}
                            <strong className="text-foreground">{settlement?.period_end}</strong> will:
                        </p>
                        <ul className="list-disc space-y-1 pl-5 text-xs">
                            <li>Generate immutable double-entry transfer postings into the ledger.</li>
                            <li>Set temporal boundaries on active member financial profiles.</li>
                            <li>Lock past transactions from retroactive edits.</li>
                        </ul>

                        <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400">
                            Type <strong>CONFIRM</strong> below to authorize locking this period.
                        </div>

                        <Input
                            type="text"
                            placeholder="Type CONFIRM"
                            value={confirmText}
                            onChange={(e) => setConfirmText(e.target.value)}
                            className="h-10 font-mono uppercase"
                        />
                    </div>

                    {mutation.isError && (
                        <p className="mt-2 text-xs text-destructive">
                            {(mutation.error as Error)?.message || 'Failed to execute settlement.'}
                        </p>
                    )}

                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setIsConfirmOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={confirmText.trim() !== 'CONFIRM' || mutation.isPending}
                            onClick={() => mutation.mutate()}
                        >
                            Execute Settlement & Lock
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={selectedTransfer !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedTransfer(null);
                        transferMutation.reset();
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Record mid-cycle transfer</DialogTitle>
                        <DialogDescription>
                            Apply this payment to the open cycle now instead of waiting for final settlement.
                        </DialogDescription>
                    </DialogHeader>

                    {selectedTransfer && settlement && (
                        <Card size="sm">
                            <CardHeader>
                                <CardTitle>{selectedTransfer.instruction}</CardTitle>
                                <CardDescription>
                                    Cycle {settlement.period_start} to {settlement.period_end}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex items-end justify-between gap-4">
                                <p className="text-xs text-muted-foreground">
                                    Account #{selectedTransfer.from_account_id} to Account #
                                    {selectedTransfer.to_account_id}
                                </p>
                                <p className="shrink-0 font-mono text-xl font-bold text-inflow">
                                    {centsToCurrency(selectedTransfer.amount, currencySymbol)}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {transferMutation.isError && (
                        <p className="text-xs text-destructive">
                            {(transferMutation.error as Error)?.message || 'Failed to record the transfer.'}
                        </p>
                    )}

                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setSelectedTransfer(null)}>
                            Cancel
                        </Button>
                        <Button
                            disabled={!selectedTransfer || transferMutation.isPending}
                            onClick={() => transferMutation.mutate()}
                        >
                            {transferMutation.isPending && <Spinner data-icon="inline-start" />}
                            Record transfer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
