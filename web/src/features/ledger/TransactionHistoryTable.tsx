import { useMemo } from 'react';
import { formatEuroFromCents } from '../../lib/currency';
import { useTransactionsQuery } from '../../api/transactions';

interface TransactionHistoryTableProps {
  ledgerId: number;
}

export function TransactionHistoryTable({ ledgerId }: TransactionHistoryTableProps) {
  const { data, isPending, isError, error } = useTransactionsQuery(ledgerId);

  const rows = useMemo(() => data ?? [], [data]);

  if (isPending) {
    return <p className="text-muted-foreground">Loading transaction history...</p>;
  }

  if (isError) {
    return <p className="text-destructive">Failed to load transactions: {(error as Error).message}</p>;
  }

  if (rows.length === 0) {
    return <p className="text-muted-foreground">No transactions yet.</p>;
  }

  return (
    <div className="overflow-x-auto rounded-card border border-border">
      <table className="min-w-full text-sm">
        <thead className="bg-surface text-muted-foreground">
          <tr>
            <th className="px-3 py-2 text-left">Date</th>
            <th className="px-3 py-2 text-left">Description</th>
            <th className="px-3 py-2 text-left">Split rule</th>
            <th className="px-3 py-2 text-right">Amount</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((transaction) => (
            <tr key={transaction.id} className="border-t border-border">
              <td className="px-3 py-2 text-muted-foreground">{transaction.date}</td>
              <td className="px-3 py-2 text-foreground">{transaction.description ?? '-'}</td>
              <td className="px-3 py-2 text-muted-foreground">{transaction.split_rule}</td>
              <td className="amount-numeric px-3 py-2 text-foreground">
                {formatEuroFromCents(transaction.amount)}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

