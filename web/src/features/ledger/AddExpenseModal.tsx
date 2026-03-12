import { zodResolver } from '@hookform/resolvers/zod';
import { useMutationState } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';
import {
  type CreateTransactionInput,
  useCreateTransactionMutation,
} from '../../api/transactions';
import type { Account } from '../../api/types';

interface AddExpenseModalProps {
  ledgerId: number;
  accounts: Account[];
}

const formSchema = z.object({
  payer_account_id: z.number().int().min(1, 'Payer account is required'),
  amount_major: z.number().positive('Amount must be greater than 0'),
  description: z.string().max(255).optional(),
  date: z.string().min(1, 'Date is required'),
  split_rule: z.enum(['equal', 'individual', 'proportional']),
});

type FormValues = z.infer<typeof formSchema>;

function toCents(value: number): number {
  return Math.round(value * 100);
}

export function AddExpenseModal({ ledgerId, accounts }: AddExpenseModalProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [selectedParticipants, setSelectedParticipants] = useState<number[]>([]);
  const [individualAmounts, setIndividualAmounts] = useState<Record<number, string>>({});
  const [participantsError, setParticipantsError] = useState<string | null>(null);

  const createTransactionMutation = useCreateTransactionMutation(ledgerId);
  const pendingTransactions = useMutationState({
    filters: { mutationKey: ['createTransaction', ledgerId], status: 'pending' },
  });

  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      description: '',
      split_rule: 'equal',
      date: new Date().toISOString().slice(0, 10),
    },
  });

  const splitRule = useWatch({ control: form.control, name: 'split_rule' });
  const isIndividual = splitRule === 'individual';

  const canSubmit = useMemo(() => {
    return pendingTransactions.length === 0 && !createTransactionMutation.isPending;
  }, [pendingTransactions.length, createTransactionMutation.isPending]);

  function toggleParticipant(accountId: number): void {
    setParticipantsError(null);
    setSelectedParticipants((current) => {
      if (current.includes(accountId)) {
        return current.filter((id) => id !== accountId);
      }

      return [...current, accountId];
    });
  }

  async function onSubmit(values: FormValues): Promise<void> {
    if (selectedParticipants.length === 0) {
      setParticipantsError('Select at least one participant account.');
      return;
    }

    const participants: CreateTransactionInput['participants'] = selectedParticipants.map((accountId) => {
      if (!isIndividual) {
        return { account_id: accountId };
      }

      const rawValue = individualAmounts[accountId];
      const parsed = rawValue === undefined || rawValue.trim() === '' ? Number.NaN : Number(rawValue);

      return {
        account_id: accountId,
        amount: Number.isFinite(parsed) ? toCents(parsed) : undefined,
      };
    });

    if (isIndividual && participants.some((participant) => participant.amount === undefined || participant.amount <= 0)) {
      setParticipantsError('Every participant requires an amount for individual split.');
      return;
    }

    if (isIndividual) {
      const totalParticipantAmount = participants.reduce((sum, participant) => sum + (participant.amount ?? 0), 0);
      const totalAmount = toCents(values.amount_major);

      if (totalParticipantAmount !== totalAmount) {
        setParticipantsError('Individual participant amounts must equal the total amount.');
        return;
      }
    }

    await createTransactionMutation.mutateAsync({
      payer_account_id: values.payer_account_id,
      amount: toCents(values.amount_major),
      description: values.description,
      date: values.date,
      split_rule: values.split_rule,
      participants,
      type: 'manual',
    });

    form.reset({
      description: '',
      split_rule: 'equal',
      date: new Date().toISOString().slice(0, 10),
    });
    setSelectedParticipants([]);
    setIndividualAmounts({});
    setParticipantsError(null);
    setIsOpen(false);
  }

  return (
    <section className="rounded-xl border border-slate-800 bg-slate-900 p-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold">Add Expense</h2>
        <button
          className="rounded bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-500"
          onClick={() => setIsOpen((current) => !current)}
          type="button"
        >
          {isOpen ? 'Close' : 'New expense'}
        </button>
      </div>

      {isOpen && (
        <form className="mt-4 grid gap-3" onSubmit={form.handleSubmit(onSubmit)}>
          <label className="grid gap-1 text-sm">
            <span>Payer account</span>
            <select
              className="rounded border border-slate-700 bg-slate-950 px-2 py-2"
              {...form.register('payer_account_id', { valueAsNumber: true })}
            >
              <option value="">Select payer</option>
              {accounts.map((account) => (
                <option key={account.id} value={account.id}>
                  {account.name}
                </option>
              ))}
            </select>
          </label>

          <label className="grid gap-1 text-sm">
            <span>Amount (major currency)</span>
            <input
              className="rounded border border-slate-700 bg-slate-950 px-2 py-2"
              step="0.01"
              type="number"
              {...form.register('amount_major', { valueAsNumber: true })}
            />
          </label>

          <label className="grid gap-1 text-sm">
            <span>Description</span>
            <input
              className="rounded border border-slate-700 bg-slate-950 px-2 py-2"
              type="text"
              {...form.register('description')}
            />
          </label>

          <label className="grid gap-1 text-sm">
            <span>Date</span>
            <input
              className="rounded border border-slate-700 bg-slate-950 px-2 py-2"
              type="date"
              {...form.register('date')}
            />
          </label>

          <label className="grid gap-1 text-sm">
            <span>Split rule</span>
            <select
              className="rounded border border-slate-700 bg-slate-950 px-2 py-2"
              {...form.register('split_rule')}
            >
              <option value="equal">Equal</option>
              <option value="individual">Individual</option>
              <option value="proportional">Proportional</option>
            </select>
          </label>

          <fieldset className="rounded border border-slate-800 p-3">
            <legend className="px-1 text-sm text-slate-300">Participants</legend>
            <div className="grid gap-2">
              {accounts.map((account) => {
                const checked = selectedParticipants.includes(account.id);

                return (
                  <div className="grid gap-2 sm:grid-cols-[auto,1fr,140px] sm:items-center" key={account.id}>
                    <label className="flex items-center gap-2 text-sm">
                      <input
                        checked={checked}
                        onChange={() => toggleParticipant(account.id)}
                        type="checkbox"
                      />
                      <span>{account.name}</span>
                    </label>
                    <span className="text-xs text-slate-400">Account #{account.id}</span>
                    {isIndividual && checked ? (
                      <input
                        className="rounded border border-slate-700 bg-slate-950 px-2 py-1 text-sm"
                        onChange={(event) => {
                          const value = event.target.value;
                          setIndividualAmounts((current) => ({
                            ...current,
                            [account.id]: value,
                          }));
                        }}
                        placeholder="Amount"
                        step="0.01"
                        type="number"
                        value={individualAmounts[account.id] ?? ''}
                      />
                    ) : (
                      <span />
                    )}
                  </div>
                );
              })}
            </div>
          </fieldset>

          {(participantsError !== null || createTransactionMutation.isError) && (
            <p className="text-sm text-red-400">
              {participantsError ?? (createTransactionMutation.error as Error).message}
            </p>
          )}

          {Object.keys(form.formState.errors).length > 0 && (
            <p className="text-sm text-red-400">
              Please fix the form errors before submitting.
            </p>
          )}

          <button
            className="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
            disabled={!canSubmit}
            type="submit"
          >
            {createTransactionMutation.isPending ? 'Saving...' : 'Save expense'}
          </button>
        </form>
      )}
    </section>
  );
}

