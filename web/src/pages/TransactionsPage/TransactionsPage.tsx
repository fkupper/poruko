import * as React from 'react';
import { useSearchParams } from 'react-router-dom';
import type { Transaction } from '@/api/types';
import { AddExpenseModal } from '@/features/transactions/AddExpenseModal/AddExpenseModal';
import { PendingApprovalTable } from '@/features/transactions/PendingApprovalTable/PendingApprovalTable';
import { TransactionsTable } from '@/features/transactions/TransactionsTable/TransactionsTable';
import { Button } from '@/components/ui/button';
import { ArrowLeftRightIcon, PlusIcon } from 'lucide-react';

export default function TransactionsPage() {
    const [searchParams] = useSearchParams();
    const [isAddExpenseOpen, setIsAddExpenseOpen] = React.useState(false);
    const [editingTransaction, setEditingTransaction] = React.useState<Transaction | null>(null);

    const accountParam = searchParams.get('account');
    const initialAccountId = accountParam ? Number(accountParam) : undefined;

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
                <Button
                    onClick={() => {
                        setEditingTransaction(null);
                        setIsAddExpenseOpen(true);
                    }}
                    className="gap-2 shrink-0"
                >
                    <PlusIcon className="size-4" />
                    Log Expense
                </Button>
            </div>

            <PendingApprovalTable />

            <TransactionsTable
                initialAccountId={initialAccountId}
                onEditTransaction={(tx) => {
                    setEditingTransaction(tx);
                    setIsAddExpenseOpen(true);
                }}
            />

            <AddExpenseModal
                open={isAddExpenseOpen}
                onOpenChange={(open) => {
                    setIsAddExpenseOpen(open);
                    if (!open) setEditingTransaction(null);
                }}
                transaction={editingTransaction}
            />
        </div>
    );
}
