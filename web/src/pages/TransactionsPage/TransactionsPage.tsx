import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchTransactions } from '@/api/transactions';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { AddExpenseModal } from '@/features/transactions/AddExpenseModal/AddExpenseModal';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowLeftRightIcon, PlusIcon, SearchIcon } from 'lucide-react';

export default function TransactionsPage() {
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    const [isAddExpenseOpen, setIsAddExpenseOpen] = React.useState(false);
    const [searchQuery, setSearchQuery] = React.useState('');

    const { data: transactions = [], isPending } = useQuery({
        queryKey: ['transactions', activeLedgerId],
        queryFn: () => fetchTransactions(activeLedgerId!),
        enabled: !!activeLedgerId,
    });

    const filteredTransactions = React.useMemo(() => {
        if (!searchQuery.trim()) return transactions;
        const q = searchQuery.toLowerCase();
        return transactions.filter(
            (tx) =>
                tx.description.toLowerCase().includes(q) ||
                tx.split_rule.toLowerCase().includes(q) ||
                tx.date.includes(q)
        );
    }, [transactions, searchQuery]);

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <ArrowLeftRightIcon className="size-6 text-primary" />
                        Transactions History
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        All recorded household expenses, split postings, and ledger history.
                    </p>
                </div>
                <Button onClick={() => setIsAddExpenseOpen(true)} className="gap-2 shrink-0">
                    <PlusIcon className="size-4" />
                    Log Expense
                </Button>
            </div>

            {/* Filter / Search Bar */}
            <div className="flex items-center gap-3">
                <div className="relative flex-1">
                    <SearchIcon className="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <input
                        type="text"
                        placeholder="Filter transactions by description or split rule..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="h-10 w-full rounded-lg border border-input bg-transparent pl-9 pr-3 py-1 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    />
                </div>
            </div>

            {/* Transactions Data Table */}
            {isPending ? (
                <div className="h-48 rounded-xl border bg-muted/20 animate-pulse flex items-center justify-center text-sm text-muted-foreground">
                    Loading ledger transactions...
                </div>
            ) : filteredTransactions.length === 0 ? (
                <div className="rounded-xl border border-dashed p-12 text-center text-muted-foreground text-sm">
                    No matching transactions found.
                </div>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>ID</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead>Split Rule</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead className="text-right">Amount</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filteredTransactions.map((tx) => (
                            <TableRow key={tx.id}>
                                <TableCell className="font-mono text-xs text-muted-foreground">#{tx.id}</TableCell>
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

            <AddExpenseModal open={isAddExpenseOpen} onOpenChange={setIsAddExpenseOpen} />
        </div>
    );
}
