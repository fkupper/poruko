import { zodResolver } from '@hookform/resolvers/zod';
import { useFieldArray, useForm } from 'react-hook-form';
import { z } from 'zod';
import {
  useFinancialProfileQuery,
  useUpdateFinancialProfileMutation,
} from '../../api/financialProfiles';

interface MyFinancesPageProps {
  ledgerId: number;
  userId: number;
}

const lineItemSchema = z.object({
  description: z.string().min(1, 'Description is required').max(255),
  amount_major: z.number().min(0, 'Amount must be 0 or greater'),
});

const financialProfileSchema = z.object({
  incomes: z.array(lineItemSchema).min(1, 'At least one income entry is required'),
  deductions: z.array(lineItemSchema),
});

type FinancialProfileFormValues = z.infer<typeof financialProfileSchema>;

function toCents(value: number): number {
  return Math.round(value * 100);
}

function toMajor(cents: number): number {
  return cents / 100;
}

export function MyFinancesPage({ ledgerId, userId }: MyFinancesPageProps) {
  const { data: profile, isPending, isError, error } = useFinancialProfileQuery(ledgerId, userId);
  const updateMutation = useUpdateFinancialProfileMutation(ledgerId, userId);

  const form = useForm<FinancialProfileFormValues>({
    resolver: zodResolver(financialProfileSchema),
    values: profile
      ? {
          incomes: profile.incomes.map((item) => ({
            description: item.description,
            amount_major: toMajor(item.amount),
          })),
          deductions: profile.deductions.map((item) => ({
            description: item.description,
            amount_major: toMajor(item.amount),
          })),
        }
      : {
          incomes: [{ description: '', amount_major: 0 }],
          deductions: [],
        },
  });

  const incomesArray = useFieldArray({ control: form.control, name: 'incomes' });
  const deductionsArray = useFieldArray({ control: form.control, name: 'deductions' });

  async function onSubmit(values: FinancialProfileFormValues): Promise<void> {
    await updateMutation.mutateAsync({
      incomes: values.incomes.map((item) => ({
        description: item.description,
        amount: toCents(item.amount_major),
      })),
      deductions: values.deductions.map((item) => ({
        description: item.description,
        amount: toCents(item.amount_major),
      })),
    });
  }

  return (
    <main className="mx-auto grid max-w-5xl gap-6 px-4 py-8">
      <section className="rounded-xl border border-slate-800 bg-slate-900 p-4">
        <h1 className="text-2xl font-semibold">My Finances</h1>
        <p className="mt-1 text-sm text-slate-400">Ledger #{ledgerId}</p>

        {isPending && <p className="mt-4 text-slate-300">Loading financial profile...</p>}
        {isError && !error.message.includes('status 404') && (
          <p className="mt-4 text-red-400">{(error as Error).message}</p>
        )}
        {isError && error.message.includes('status 404') && (
          <p className="mt-4 text-slate-400">No financial profile set up yet. Fill out the form below to create one.</p>
        )}

        <form className="mt-4 grid gap-6" onSubmit={form.handleSubmit(onSubmit)}>
          <fieldset className="rounded border border-slate-800 p-3">
            <legend className="px-1 text-sm font-medium text-slate-300">Incomes</legend>
            <div className="grid gap-3">
              {incomesArray.fields.map((field, index) => (
                <div className="grid gap-2 sm:grid-cols-[1fr,140px,auto] sm:items-end" key={field.id}>
                  <label className="grid gap-1 text-sm">
                    <span>Description</span>
                    <input
                      className="rounded border border-slate-700 bg-slate-950 px-2 py-2"
                      type="text"
                      {...form.register(`incomes.${index}.description`)}
                    />
                  </label>
                  <label className="grid gap-1 text-sm">
                    <span>Amount</span>
                    <input
                      className="rounded border border-slate-700 bg-slate-950 px-2 py-2"
                      step="0.01"
                      type="number"
                      {...form.register(`incomes.${index}.amount_major`, { valueAsNumber: true })}
                    />
                  </label>
                  <button
                    className="rounded border border-red-800 px-2 py-2 text-sm text-red-400 hover:bg-red-900/30"
                    disabled={incomesArray.fields.length <= 1}
                    onClick={() => incomesArray.remove(index)}
                    type="button"
                  >
                    Remove
                  </button>
                </div>
              ))}
              <button
                className="justify-self-start rounded border border-slate-700 px-3 py-1 text-sm text-slate-300 hover:border-slate-500"
                onClick={() => incomesArray.append({ description: '', amount_major: 0 })}
                type="button"
              >
                + Add income
              </button>
            </div>
            {form.formState.errors.incomes?.message && (
              <p className="mt-2 text-sm text-red-400">{form.formState.errors.incomes.message}</p>
            )}
          </fieldset>

          <fieldset className="rounded border border-slate-800 p-3">
            <legend className="px-1 text-sm font-medium text-slate-300">Deductions</legend>
            <div className="grid gap-3">
              {deductionsArray.fields.map((field, index) => (
                <div className="grid gap-2 sm:grid-cols-[1fr,140px,auto] sm:items-end" key={field.id}>
                  <label className="grid gap-1 text-sm">
                    <span>Description</span>
                    <input
                      className="rounded border border-slate-700 bg-slate-950 px-2 py-2"
                      type="text"
                      {...form.register(`deductions.${index}.description`)}
                    />
                  </label>
                  <label className="grid gap-1 text-sm">
                    <span>Amount</span>
                    <input
                      className="rounded border border-slate-700 bg-slate-950 px-2 py-2"
                      step="0.01"
                      type="number"
                      {...form.register(`deductions.${index}.amount_major`, { valueAsNumber: true })}
                    />
                  </label>
                  <button
                    className="rounded border border-red-800 px-2 py-2 text-sm text-red-400 hover:bg-red-900/30"
                    onClick={() => deductionsArray.remove(index)}
                    type="button"
                  >
                    Remove
                  </button>
                </div>
              ))}
              <button
                className="justify-self-start rounded border border-slate-700 px-3 py-1 text-sm text-slate-300 hover:border-slate-500"
                onClick={() => deductionsArray.append({ description: '', amount_major: 0 })}
                type="button"
              >
                + Add deduction
              </button>
            </div>
          </fieldset>

          {profile?.computed && (
            <div className="rounded border border-slate-800 bg-slate-950 p-3">
              <h3 className="text-sm font-medium text-slate-300">Current Summary</h3>
              <dl className="mt-2 grid grid-cols-3 gap-4 text-center text-sm">
                <div>
                  <dt className="text-slate-400">Total Income</dt>
                  <dd className="text-lg font-semibold text-emerald-400">
                    {(profile.computed.total_income / 100).toFixed(2)}
                  </dd>
                </div>
                <div>
                  <dt className="text-slate-400">Total Deductions</dt>
                  <dd className="text-lg font-semibold text-red-400">
                    {(profile.computed.total_deductions / 100).toFixed(2)}
                  </dd>
                </div>
                <div>
                  <dt className="text-slate-400">Shareable Income</dt>
                  <dd className="text-lg font-semibold text-indigo-400">
                    {(profile.computed.shareable_income / 100).toFixed(2)}
                  </dd>
                </div>
              </dl>
            </div>
          )}

          {updateMutation.isError && (
            <p className="text-sm text-red-400">{(updateMutation.error as Error).message}</p>
          )}

          {updateMutation.isSuccess && (
            <p className="text-sm text-emerald-400">Financial profile saved successfully.</p>
          )}

          <button
            className="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
            disabled={updateMutation.isPending}
            type="submit"
          >
            {updateMutation.isPending ? 'Saving...' : 'Save profile'}
          </button>
        </form>
      </section>
    </main>
  );
}
