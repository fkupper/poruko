import { useMemo } from 'react';
import { useTransactionsQuery } from '../../api/transactions';

interface TransactionHistoryTableProps {
  ledgerId: number;
}

function formatCurrencyFromCents(cents: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'EUR',
  }).format(cents / 100);
}

export function TransactionHistoryTable({ ledgerId }: TransactionHistoryTableProps) {
  const { data, isPending, isError, error } = useTransactionsQuery(ledgerId);

  const rows = useMemo(() => data ?? [], [data]);

  if (isPending) {
    return <p className="text-slate-300">Loading transaction history...</p>;
  }

  if (isError) {
    return <p className="text-red-400">Failed to load transactions: {(error as Error).message}</p>;
  }

  if (rows.length === 0) {
    return <p className="text-slate-400">No transactions yet.</p>;
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-slate-800">
      <table className="min-w-full text-sm">
        <thead className="bg-slate-900 text-slate-300">
          <tr>
            <th className="px-3 py-2 text-left">Date</th>
            <th className="px-3 py-2 text-left">Description</th>
            <th className="px-3 py-2 text-left">Split rule</th>
            <th className="px-3 py-2 text-right">Amount</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((transaction) => (
            <tr key={transaction.id} className="border-t border-slate-800">
              <td className="px-3 py-2 text-slate-300">{transaction.date}</td>
              <td className="px-3 py-2 text-slate-100">{transaction.description ?? '-'}</td>
              <td className="px-3 py-2 text-slate-300">{transaction.split_rule}</td>
              <td className="px-3 py-2 text-right text-slate-100">
                {formatCurrencyFromCents(transaction.amount)}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

