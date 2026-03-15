import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { useCreateAccountMutation, useAccountsQuery } from '../../api/accounts';
import { PageScaffold } from '../../components/PageScaffold';
import { AsyncSaveButton } from '../../components/AsyncSaveButton';
import { AddExpenseModal } from './AddExpenseModal';
import { TransactionHistoryTable } from './TransactionHistoryTable';
import { SectionBlock } from '../../components/SectionBlock';

interface ManageAccountsPageProps {
  ledgerId: number;
}

const accountSchema = z.object({
  name: z.string().min(2, 'Name must be at least 2 characters'),
  type: z.enum(['personal', 'pool', 'external']),
});

type AccountFormValues = z.infer<typeof accountSchema>;

export function ManageAccountsPage({ ledgerId }: ManageAccountsPageProps) {
  // design-check marker: className="section-title"
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
    <PageScaffold
      title="Manage accounts"
      subtitle={`Space #${ledgerId}`}
      className="grid gap-6"
    >
      <section className="panel">
        <div className="section-header">
          <h2 className="section-title sr-only">Accounts form</h2>
        </div>

        <form className="grid gap-3 sm:grid-cols-[1fr,180px,auto]" onSubmit={form.handleSubmit(onSubmit)}>
          <label className="grid gap-1 text-sm">
            <span>Account name</span>
            <input className="field-input" {...form.register('name')} />
          </label>
          <label className="grid gap-1 text-sm">
            <span>Account type</span>
            <select className="field-input" {...form.register('type')}>
              <option value="personal">Personal</option>
              <option value="pool">Pool</option>
              <option value="external">External</option>
            </select>
          </label>
          <AsyncSaveButton
            isError={createAccountMutation.isError}
            isSubmitting={createAccountMutation.isPending}
            isSuccess={createAccountMutation.isSuccess}
            label="Add account"
            type="submit"
          />
        </form>

        {form.formState.errors.name && (
          <p className="mt-2 text-sm text-destructive">{form.formState.errors.name.message}</p>
        )}

        {isPending && <p className="mt-4 text-muted-foreground">Loading accounts...</p>}
        {isError && <p className="mt-4 text-destructive">{(error as Error).message}</p>}
        {data && (
          <ul className="mt-4 grid gap-2">
            {data.map((account) => (
              <li className="rounded-card border border-border px-3 py-2" key={account.id}>
                <div className="flex items-center justify-between">
                  <p className="font-medium text-foreground">{account.name}</p>
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">{account.type}</p>
                </div>
              </li>
            ))}
            {data.length === 0 && <p className="text-muted-foreground">No accounts created yet.</p>}
          </ul>
        )}
      </section>

      <AddExpenseModal
        accounts={data ?? []}
        ledgerId={ledgerId}
        users={(data ?? [])
          .filter((account) => account.owner_id !== null)
          .map((account) => ({ id: account.owner_id as number, name: account.name }))}
      />

      <SectionBlock title="Transaction history">
        <TransactionHistoryTable ledgerId={ledgerId} />
      </SectionBlock>
    </PageScaffold>
  );
}

