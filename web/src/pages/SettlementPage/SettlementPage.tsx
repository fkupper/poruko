import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchSettlementPreview, executeSettlement } from '@/api/settlements';
import { centsToCurrency, formatPercent } from '@/lib/currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogClose, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    AlertTriangleIcon,
    ArrowRightIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    HandCoinsIcon,
    LockIcon,
    ShieldAlertIcon,
} from 'lucide-react';

export default function SettlementPage() {
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);

    const [isConfirmOpen, setIsConfirmOpen] = React.useState(false);
    const [showMathBreakdown, setShowMathBreakdown] = React.useState(true);
    const [confirmText, setConfirmText] = React.useState('');

    const { data: settlement, isPending, isError, refetch } = useQuery({
        queryKey: ['settlement-preview', activeLedgerId],
        queryFn: () => fetchSettlementPreview(activeLedgerId!),
        enabled: !!activeLedgerId,
    });

    const mutation = useMutation({
        mutationFn: async () => {
            if (!activeLedgerId || !settlement) throw new Error('No active settlement preview.');
            await executeSettlement(activeLedgerId, settlement.period_end);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['settlement-preview', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['transactions', activeLedgerId] });
            setIsConfirmOpen(false);
            setConfirmText('');
        },
    });

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <HandCoinsIcon className="size-6 text-emerald-500" />
                        End-of-Month Settlement Engine
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Calculate true-up contributions, review math breakdowns, and lock period boundaries.
                    </p>
                </div>
                {settlement && (
                    <Button
                        onClick={() => setIsConfirmOpen(true)}
                        className="gap-2 shrink-0 bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
                    >
                        <LockIcon className="size-4" />
                        Execute & Lock Month
                    </Button>
                )}
            </div>

            {isPending ? (
                <div className="h-64 rounded-xl border bg-muted/20 animate-pulse flex items-center justify-center text-sm text-muted-foreground">
                    Calculating settlement preview...
                </div>
            ) : isError || !settlement ? (
                <div className="rounded-xl border border-destructive/20 bg-destructive/10 p-6 text-center space-y-3">
                    <AlertTriangleIcon className="size-8 text-destructive mx-auto" />
                    <p className="text-sm font-medium text-destructive">Failed to calculate settlement preview.</p>
                    <Button variant="outline" size="sm" onClick={() => void refetch()}>
                        Retry Calculation
                    </Button>
                </div>
            ) : (
                <>
                    {/* Period Banner */}
                    <div className="rounded-xl border bg-muted/30 p-4 flex flex-wrap items-center justify-between gap-4 text-xs font-medium">
                        <div className="flex items-center gap-3">
                            <Badge variant="secondary">
                                Period: {settlement.period_start} to {settlement.period_end}
                            </Badge>
                            <span className="text-muted-foreground capitalize">
                                Mode: {settlement.settlement_mode.replace('_', ' ')}
                            </span>
                        </div>
                        <div className="flex items-center gap-4 font-mono">
                            <span>
                                Total Spend:{' '}
                                <strong className="text-foreground">
                                    {centsToCurrency(settlement.summary.total_shared_spend)}
                                </strong>
                            </span>
                            <span>
                                Pool Balance:{' '}
                                <strong className="text-foreground">
                                    {centsToCurrency(settlement.summary.pool_current_balance)}
                                </strong>
                            </span>
                        </div>
                    </div>

                    {/* Plain Language Action Cards */}
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">
                            Required Transfer Actions
                        </h2>
                        {settlement.required_transfers.length === 0 ? (
                            <div className="rounded-xl border bg-card p-6 text-center text-sm text-muted-foreground">
                                All members are completely balanced. No transfer instructions needed!
                            </div>
                        ) : (
                            <div className="grid gap-3">
                                {settlement.required_transfers.map((tx, idx) => (
                                    <Card key={idx} className="border-l-4 border-l-emerald-500">
                                        <CardContent className="pt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                            <div className="flex items-center gap-3">
                                                <div className="flex size-10 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600">
                                                    <ArrowRightIcon className="size-5" />
                                                </div>
                                                <div>
                                                    <p className="text-base font-semibold text-foreground">
                                                        {tx.instruction}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        From Account #{tx.from_account_id} to Joint Account #{tx.to_account_id}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                                                {centsToCurrency(tx.amount)}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Expandable Math Breakdown */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
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
                                        Hide Breakdown <ChevronUpIcon className="size-4" />
                                    </>
                                ) : (
                                    <>
                                        Show Breakdown <ChevronDownIcon className="size-4" />
                                    </>
                                )}
                            </Button>
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
                                                    {centsToCurrency(user.target_liability)}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-muted-foreground">
                                                    {centsToCurrency(user.paid_out_of_pocket)}
                                                </TableCell>
                                                <TableCell
                                                    className={`text-right font-mono font-bold ${
                                                        user.net_balance < 0
                                                            ? 'text-orange-600 dark:text-orange-400'
                                                            : 'text-emerald-600 dark:text-emerald-400'
                                                    }`}
                                                >
                                                    {centsToCurrency(user.net_balance)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        )}
                    </Card>
                </>
            )}

            {/* High-Friction Confirmation Modal */}
            <Dialog open={isConfirmOpen} onOpenChange={setIsConfirmOpen}>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-destructive">
                        <ShieldAlertIcon className="size-5" />
                        High-Friction Action: Confirm Settlement Execution
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4 text-sm text-muted-foreground">
                    <p>
                        Executing settlement for period ending{' '}
                        <strong className="text-foreground">{settlement?.period_end}</strong> will:
                    </p>
                    <ul className="list-disc pl-5 space-y-1 text-xs">
                        <li>Generate immutable double-entry transfer postings into the ledger.</li>
                        <li>Set temporal boundaries on active member financial profiles.</li>
                        <li>Lock past transactions from retroactive edits.</li>
                    </ul>

                    <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400">
                        Type <strong>CONFIRM</strong> below to authorize locking this period.
                    </div>

                    <input
                        type="text"
                        placeholder="Type CONFIRM"
                        value={confirmText}
                        onChange={(e) => setConfirmText(e.target.value)}
                        className="h-10 w-full rounded-lg border border-input bg-transparent px-3 py-1 font-mono text-sm uppercase outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                </div>

                {mutation.isError && (
                    <p className="text-xs text-destructive mt-2">
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
                <DialogClose onClose={() => setIsConfirmOpen(false)} />
            </Dialog>
        </div>
    );
}
