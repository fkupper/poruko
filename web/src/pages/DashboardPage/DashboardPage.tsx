import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { fetchTransactions } from '@/api/transactions';
import { fetchSettlementPreview } from '@/api/settlements';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { useAuthStore } from '@/stores/authStore';
import { AddExpenseModal } from '@/features/transactions/AddExpenseModal/AddExpenseModal';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
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
    const currentUser = useAuthStore((s) => s.user);
    const [isAddExpenseOpen, setIsAddExpenseOpen] = React.useState(false);

    // Fetch transactions
    const { data: transactions = [], isPending: isLoadingTx } = useQuery({
        queryKey: ['transactions', activeLedgerId],
        queryFn: () => fetchTransactions(activeLedgerId!),
        enabled: !!activeLedgerId,
    });

    // Fetch settlement preview summary
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
        <div className="space-y-6">
            {/* Header & Quick Action */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                        Space Overview
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Real-time household ledger summary & recent financial activity.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button onClick={() => setIsAddExpenseOpen(true)} className="gap-2 shadow-xs">
                        <PlusIcon className="size-4" />
                        Log Expense
                    </Button>
                    <Button variant="outline" asChild className="gap-2">
                        <Link to="/settlement">
                            <HandCoinsIcon className="size-4 text-emerald-500" />
                            Settle Up
                        </Link>
                    </Button>
                </div>
            </div>

            {/* Quick Stats Grid */}
            <div className="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-medium text-muted-foreground">
                            Total Shared Spend
                        </CardTitle>
                        <ReceiptIcon className="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold font-mono">
                            {centsToCurrency(settlement?.summary.total_shared_spend ?? 0)}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            Current month total joint transactions
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-medium text-muted-foreground">
                            Joint Pool Balance
                        </CardTitle>
                        <WalletIcon className="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold font-mono">
                            {centsToCurrency(settlement?.summary.pool_current_balance ?? 0)}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            Available in shared pool account
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-medium text-muted-foreground">
                            My Net Balance
                        </CardTitle>
                        <DollarSignIcon className="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div
                            className={`text-2xl font-bold font-mono ${
                                (myBreakdown?.net_balance ?? 0) < 0
                                    ? 'text-orange-600 dark:text-orange-400'
                                    : 'text-emerald-600 dark:text-emerald-400'
                            }`}
                        >
                            {centsToCurrency(myBreakdown?.net_balance ?? 0)}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            {(myBreakdown?.net_balance ?? 0) < 0
                                ? 'Amount to transfer at settlement'
                                : 'You are fully settled up'}
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Settle Up Banner Widget */}
            {settlement && settlement.required_transfers.length > 0 && (
                <div className="rounded-xl border bg-gradient-to-r from-blue-500/10 via-background to-emerald-500/10 p-4 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xs">
                    <div className="space-y-1">
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
                            <ArrowUpRightIcon className="size-3.5" />
                        </Link>
                    </Button>
                </div>
            )}

            {/* Recent Activity Table */}
            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold tracking-tight text-foreground flex items-center gap-2">
                        <ArrowLeftRightIcon className="size-5 text-muted-foreground" />
                        Recent Transactions
                    </h2>
                    <Button variant="ghost" size="sm" asChild>
                        <Link to="/transactions">View All</Link>
                    </Button>
                </div>

                {isLoadingTx ? (
                    <div className="h-40 rounded-xl border bg-muted/20 animate-pulse flex items-center justify-center text-sm text-muted-foreground">
                        Loading transactions...
                    </div>
                ) : transactions.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center space-y-3">
                        <ReceiptIcon className="size-8 mx-auto text-muted-foreground" />
                        <div>
                            <p className="text-sm font-medium">No transactions logged yet</p>
                            <p className="text-xs text-muted-foreground">
                                Click "Log Expense" above to add your first household bill or spend.
                            </p>
                        </div>
                        <Button size="sm" onClick={() => setIsAddExpenseOpen(true)}>
                            Log First Expense
                        </Button>
                    </div>
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
                                        {centsToCurrency(tx.amount)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>

            {/* Modal */}
            <AddExpenseModal open={isAddExpenseOpen} onOpenChange={setIsAddExpenseOpen} />
        </div>
    );
}
