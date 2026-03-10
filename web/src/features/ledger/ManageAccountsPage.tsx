import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { useCreateAccountMutation, useAccountsQuery } from '../../api/accounts';
import { AddExpenseModal } from './AddExpenseModal';
import { TransactionHistoryTable } from './TransactionHistoryTable';

interface ManageAccountsPageProps {
  ledgerId: number;
}

const accountSchema = z.object({
  name: z.string().min(2, 'Name must be at least 2 characters'),
  type: z.enum(['personal', 'pool', 'external']),
});

type AccountFormValues = z.infer<typeof accountSchema>;

export function ManageAccountsPage({ ledgerId }: ManageAccountsPageProps) {
  const { data, isPending, isError, error } = useAccountsQuery(ledgerId);
  const createAccountMutation = useCreateAccountMutation(ledgerId);

  const form = useForm<AccountFormValues>({
    resolver: zodResolver(accountSchema),
    defaultValues: {
      name: '',
      type: 'personal',
    },
  });

  async function onSubmit(values: AccountFormValues): Promise<void> {
    await createAccountMutation.mutateAsync(values);
    form.reset({
      name: '',
      type: 'personal',
    });
  }

  return (
    <main className="mx-auto grid max-w-5xl gap-6 px-4 py-8">
      <section className="rounded-xl border border-slate-800 bg-slate-900 p-4">
        <h1 className="text-2xl font-semibold">Manage Accounts</h1>
        <p className="mt-1 text-sm text-slate-400">Ledger #{ledgerId}</p>

        <form className="mt-4 grid gap-3 sm:grid-cols-[1fr,180px,auto]" onSubmit={form.handleSubmit(onSubmit)}>
          <input
            className="rounded border border-slate-700 bg-slate-950 px-3 py-2"
            placeholder="Account name"
            {...form.register('name')}
          />
          <select
            className="rounded border border-slate-700 bg-slate-950 px-3 py-2"
            {...form.register('type')}
          >
            <option value="personal">Personal</option>
            <option value="pool">Pool</option>
            <option value="external">External</option>
          </select>
          <button
            className="rounded bg-indigo-600 px-3 py-2 font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
            disabled={createAccountMutation.isPending}
            type="submit"
          >
            {createAccountMutation.isPending ? 'Saving...' : 'Add account'}
          </button>
        </form>

        {form.formState.errors.name && (
          <p className="mt-2 text-sm text-red-400">{form.formState.errors.name.message}</p>
        )}

        {isPending && <p className="mt-4 text-slate-300">Loading accounts...</p>}
        {isError && <p className="mt-4 text-red-400">{(error as Error).message}</p>}
        {data && (
          <ul className="mt-4 grid gap-2">
            {data.map((account) => (
              <li className="rounded border border-slate-800 px-3 py-2" key={account.id}>
                <div className="flex items-center justify-between">
                  <p className="font-medium text-slate-100">{account.name}</p>
                  <p className="text-xs uppercase tracking-wide text-slate-400">{account.type}</p>
                </div>
              </li>
            ))}
            {data.length === 0 && <p className="text-slate-400">No accounts created yet.</p>}
          </ul>
        )}
      </section>

      <AddExpenseModal accounts={data ?? []} ledgerId={ledgerId} />

      <section className="rounded-xl border border-slate-800 bg-slate-900 p-4">
        <h2 className="text-lg font-semibold">Transaction History</h2>
        <div className="mt-3">
          <TransactionHistoryTable ledgerId={ledgerId} />
        </div>
      </section>
    </main>
  );
}

