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
import { AsyncSaveButton } from '../../components/AsyncSaveButton';

interface AddExpenseModalProps {
  ledgerId: number;
  accounts: Account[];
  users: Array<{ id: number; name: string }>;
}

const formSchema = z.object({
  credit_account_id: z.number().int().min(1, 'Source account is required'),
  debit_account_id: z.number().int().min(1, 'Destination account is required'),
  amount_major: z.number().positive('Amount must be greater than 0'),
  description: z.string().max(255).optional(),
  date: z.string().min(1, 'Date is required'),
  split_rule: z.enum(['equal', 'individual', 'proportional', 'manual']),
});

type FormValues = z.infer<typeof formSchema>;

function toCents(value: number): number {
  return Math.round(value * 100);
}

export function AddExpenseModal({ ledgerId, accounts, users }: AddExpenseModalProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [selectedUsers, setSelectedUsers] = useState<number[]>([]);
  const [manualShares, setManualShares] = useState<Record<number, string>>({});
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
  const isManual = splitRule === 'manual';

  const canSubmit = useMemo(() => {
    return pendingTransactions.length === 0 && !createTransactionMutation.isPending;
  }, [pendingTransactions.length, createTransactionMutation.isPending]);

  function toggleUser(userId: number): void {
    setParticipantsError(null);
    setSelectedUsers((current) => {
      if (current.includes(userId)) {
        return current.filter((id) => id !== userId);
      }

      if (isIndividual) {
        return [userId];
      }

      return [...current, userId];
    });
  }

  async function onSubmit(values: FormValues): Promise<void> {
    let participants: CreateTransactionInput['participants'] = null;

    if (selectedUsers.length > 0) {
      if (isIndividual && selectedUsers.length !== 1) {
        setParticipantsError('Individual split requires exactly 1 participant.');
        return;
      }

      if (isManual) {
        const hasInvalidShares = selectedUsers.some((userId) => {
          const raw = manualShares[userId];
          const parsed = raw === undefined || raw.trim() === '' ? Number.NaN : Number(raw);
          return !Number.isFinite(parsed) || parsed <= 0;
        });

        if (hasInvalidShares) {
          setParticipantsError('Every participant requires a share weight greater than 0 for manual split.');
          return;
        }

        participants = selectedUsers.map((userId) => ({
          user_id: userId,
          share: Number(manualShares[userId]),
        }));
      } else {
        participants = selectedUsers.map((userId) => ({ user_id: userId }));
      }
    }

    await createTransactionMutation.mutateAsync({
      credit_account_id: values.credit_account_id,
      debit_account_id: values.debit_account_id,
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
    setSelectedUsers([]);
    setManualShares({});
    setParticipantsError(null);
    setIsOpen(false);
  }

  return (
    <section className="panel">
      <div className="flex items-center justify-between">
        <h2 className="section-title">Add Expense</h2>
        <button
          className="btn-success text-sm"
          onClick={() => setIsOpen((current) => !current)}
          type="button"
        >
          {isOpen ? 'Close' : 'New expense'}
        </button>
      </div>

      {isOpen && (
        <form className="mt-4 grid gap-3" onSubmit={form.handleSubmit(onSubmit)}>
          <label className="grid gap-1 text-sm">
            <span>Source account</span>
            <select
              className="field-input-compact"
              {...form.register('credit_account_id', { valueAsNumber: true })}
            >
              <option value="">Select source</option>
              {accounts.map((account) => (
                <option key={account.id} value={account.id}>
                  {account.name}
                </option>
              ))}
            </select>
          </label>

          <label className="grid gap-1 text-sm">
            <span>Destination account</span>
            <select
              className="field-input-compact"
              {...form.register('debit_account_id', { valueAsNumber: true })}
            >
              <option value="">Select destination</option>
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
              className="field-input-compact amount-numeric"
              step="0.01"
              type="number"
              {...form.register('amount_major', { valueAsNumber: true })}
            />
          </label>

          <label className="grid gap-1 text-sm">
            <span>Description</span>
            <input
              className="field-input-compact"
              type="text"
              {...form.register('description')}
            />
          </label>

          <label className="grid gap-1 text-sm">
            <span>Date</span>
            <input
              className="field-input-compact"
              type="date"
              {...form.register('date')}
            />
          </label>

          <label className="grid gap-1 text-sm">
            <span>Split rule</span>
            <select
              className="field-input-compact"
              {...form.register('split_rule')}
            >
              <option value="equal">Equal</option>
              <option value="individual">Individual</option>
              <option value="proportional">Proportional</option>
              <option value="manual">Manual</option>
            </select>
          </label>

          <fieldset className="rounded-card border border-border p-3">
            <legend className="px-1 text-sm text-muted-foreground">Participants (optional)</legend>
            <div className="grid gap-2">
              {users.map((user) => {
                const checked = selectedUsers.includes(user.id);

                return (
                  <div className="grid gap-2 sm:grid-cols-[auto,1fr,140px] sm:items-center" key={user.id}>
                    <label className="flex items-center gap-2 text-sm">
                      <input
                        checked={checked}
                        onChange={() => toggleUser(user.id)}
                        type="checkbox"
                      />
                      <span>{user.name}</span>
                    </label>
                    <span className="text-xs text-muted-foreground">User #{user.id}</span>
                    {isManual && checked ? (
                      <input
                        className="field-input-compact amount-numeric py-1 text-sm"
                        onChange={(event) => {
                          const value = event.target.value;
                          setManualShares((current) => ({
                            ...current,
                            [user.id]: value,
                          }));
                        }}
                        placeholder="Share weight"
                        step="1"
                        type="number"
                        value={manualShares[user.id] ?? ''}
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
            <p className="text-sm text-destructive">
              {participantsError ?? (createTransactionMutation.error as Error).message}
            </p>
          )}

          {Object.keys(form.formState.errors).length > 0 && (
            <p className="text-sm text-destructive">
              Please fix the form errors before submitting.
            </p>
          )}

          <AsyncSaveButton
            className="text-sm"
            disabled={!canSubmit}
            isError={createTransactionMutation.isError}
            isSubmitting={createTransactionMutation.isPending}
            isSuccess={createTransactionMutation.isSuccess}
            label="Save expense"
            type="submit"
          />
        </form>
      )}
    </section>
  );
}
