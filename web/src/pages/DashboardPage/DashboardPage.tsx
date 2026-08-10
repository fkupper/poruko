import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { fetchTransactions } from '@/api/transactions';
import { fetchSettlementPreview } from '@/api/settlements';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { useAuthStore } from '@/stores/authStore';
import { AddExpenseModal } from '@/features/transactions/AddExpenseModal/AddExpenseModal';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    ArrowLeftRightIcon,
    ArrowUpRightIcon,
    DollarSignIcon,
    HandCoinsIcon,
    PlusIcon,
    ReceiptIcon,
    WalletIcon,
} from 'lucide-react';

export default function DashboardPage() {
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    const currencySymbol = useLedgerCurrencySymbol();
    const currentUser = useAuthStore((s) => s.user);
    const [isAddExpenseOpen, setIsAddExpenseOpen] = React.useState(false);

    const { data: transactions = [], isPending: isLoadingTx } = useQuery({
        queryKey: ['transactions', activeLedgerId],
        queryFn: () => fetchTransactions(activeLedgerId!),
        enabled: !!activeLedgerId,
    });

    const { data: settlement } = useQuery({
        queryKey: ['settlement-preview', activeLedgerId],
        queryFn: () => fetchSettlementPreview(activeLedgerId!),
        enabled: !!activeLedgerId,
    });

    const myBreakdown = React.useMemo(() => {
        if (!settlement || !currentUser) return null;
        return settlement.user_breakdowns.find((u) => u.user_id === currentUser.id);
    }, [settlement, currentUser]);

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">Space Overview</h1>
                    <p className="text-sm text-muted-foreground">
                        Real-time household ledger summary & recent financial activity.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button onClick={() => setIsAddExpenseOpen(true)} className="gap-2 shadow-xs">
                        <PlusIcon data-icon="inline-start" />
                        Log Expense
                    </Button>
                    <Button variant="outline" asChild className="gap-2">
                        <Link to="/settlement">
                            <HandCoinsIcon data-icon="inline-start" className="text-inflow" />
                            Settle Up
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-xs font-medium text-muted-foreground">
                                Total Shared Spend
                            </CardTitle>
                            <ReceiptIcon className="size-4 text-muted-foreground" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="font-mono text-2xl font-bold">
                            {centsToCurrency(settlement?.summary.total_shared_spend ?? 0, currencySymbol)}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Current month total joint transactions
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-xs font-medium text-muted-foreground">
                                Joint Pool Balance
                            </CardTitle>
                            <WalletIcon className="size-4 text-muted-foreground" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="font-mono text-2xl font-bold">
                            {centsToCurrency(settlement?.summary.pool_current_balance ?? 0, currencySymbol)}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">Available in shared pool account</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-xs font-medium text-muted-foreground">My Net Balance</CardTitle>
                            <DollarSignIcon className="size-4 text-muted-foreground" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            className={`font-mono text-2xl font-bold ${
                                (myBreakdown?.net_balance ?? 0) < 0 ? 'text-outflow' : 'text-inflow'
                            }`}
                        >
                            {centsToCurrency(myBreakdown?.net_balance ?? 0, currencySymbol)}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {(myBreakdown?.net_balance ?? 0) < 0
                                ? 'Amount to transfer at settlement'
                                : 'You are fully settled up'}
                        </p>
                    </CardContent>
                </Card>
            </div>

            {settlement && settlement.required_transfers.length > 0 && (
                <div className="flex flex-col gap-4 rounded-xl border bg-primary/5 p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-2">
                            <Badge variant="info">Settlement Ready</Badge>
                            <span className="text-xs text-muted-foreground">
                                Period ending {settlement.period_end}
                            </span>
                        </div>
                        <p className="text-sm font-medium text-foreground">
                            {settlement.required_transfers[0].instruction}
                        </p>
                    </div>
                    <Button asChild size="sm" variant="secondary" className="gap-1 shrink-0">
                        <Link to="/settlement">
                            Review Settlement
                            <ArrowUpRightIcon data-icon="inline-end" />
                        </Link>
                    </Button>
                </div>
            )}

            <div className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                    <h2 className="flex items-center gap-2 text-lg font-semibold tracking-tight text-foreground">
                        <ArrowLeftRightIcon className="size-5 text-muted-foreground" />
                        Recent Transactions
                    </h2>
                    <Button variant="ghost" size="sm" asChild>
                        <Link to="/transactions">View All</Link>
                    </Button>
                </div>

                {isLoadingTx ? (
                    <div className="flex flex-col gap-2 rounded-xl border p-4">
                        <Skeleton className="h-8 w-full" />
                        <Skeleton className="h-8 w-full" />
                        <Skeleton className="h-8 w-3/4" />
                    </div>
                ) : transactions.length === 0 ? (
                    <Empty className="border border-dashed">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ReceiptIcon />
                            </EmptyMedia>
                            <EmptyTitle>No transactions logged yet</EmptyTitle>
                            <EmptyDescription>
                                Click &quot;Log Expense&quot; above to add your first household bill or spend.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button size="sm" onClick={() => setIsAddExpenseOpen(true)}>
                                Log First Expense
                            </Button>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>Split Rule</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead className="text-right">Amount</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {transactions.slice(0, 5).map((tx) => (
                                <TableRow key={tx.id}>
                                    <TableCell className="font-mono text-xs">{tx.date}</TableCell>
                                    <TableCell className="font-medium">{tx.description}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline" className="capitalize text-[11px]">
                                            {tx.split_rule}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="capitalize text-xs text-muted-foreground">
                                        {tx.type}
                                    </TableCell>
                                    <TableCell className="text-right font-mono font-semibold text-foreground">
                                        {centsToCurrency(tx.amount, currencySymbol)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>

            <AddExpenseModal open={isAddExpenseOpen} onOpenChange={setIsAddExpenseOpen} />
        </div>
    );
}
