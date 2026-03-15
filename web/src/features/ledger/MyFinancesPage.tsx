import { zodResolver } from '@hookform/resolvers/zod';
import { DollarSign, TrendingUp, XCircle } from 'lucide-react';
import { useFieldArray, useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';
import { formatEuroFromCents } from '../../lib/currency';
import {
  useFinancialProfileQuery,
  useUpdateFinancialProfileMutation,
} from '../../api/financialProfiles';
import { EditableLineItem } from '../../components/EditableLineItem';
import { AsyncSaveButton } from '../../components/AsyncSaveButton';
import { PageScaffold } from '../../components/PageScaffold';
import { SectionBlock } from '../../components/SectionBlock';
import { SummaryCard } from '../../components/SummaryCard';

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

function formatEuroFromMajor(value: number): string {
  return formatEuroFromCents(toCents(value));
}

function safeMajorAmount(value: number): number {
  if (!Number.isFinite(value)) {
    return 0;
  }
  return Math.max(0, value);
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
  const watchedIncomes = useWatch({ control: form.control, name: 'incomes' }) ?? [];
  const watchedDeductions = useWatch({ control: form.control, name: 'deductions' }) ?? [];
  const totalIncomeMajor = watchedIncomes.reduce((total, item) => {
    return total + safeMajorAmount(item.amount_major);
  }, 0);
  const totalDeductionsMajor = watchedDeductions.reduce((total, item) => {
    return total + safeMajorAmount(item.amount_major);
  }, 0);
  const shareableIncomeMajor = totalIncomeMajor - totalDeductionsMajor;

  const isNotFoundError = isError && (error as Error).message.includes('status 404');

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
    <PageScaffold
      title="My Finances"
      subtitle="Set your income and deductions to calculate your shareable income for proportional splits."
    >
      <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <SummaryCard
          amount={formatEuroFromMajor(totalIncomeMajor)}
          descriptor={`${watchedIncomes.length} income ${watchedIncomes.length === 1 ? 'source' : 'sources'}`}
          icon={<TrendingUp size={14} />}
          label="Total Monthly Income"
          tone="inflow"
        />
        <SummaryCard
          amount={formatEuroFromMajor(totalDeductionsMajor)}
          descriptor={`${watchedDeductions.length} deduction${watchedDeductions.length === 1 ? '' : 's'}`}
          icon={<XCircle size={14} />}
          label="Total Deductions"
          tone="destructive"
        />
        <SummaryCard
          amount={formatEuroFromMajor(shareableIncomeMajor)}
          descriptor="Used for proportional splits"
          icon={<DollarSign size={14} />}
          label="Shareable Income"
          tone="info"
        />
      </section>

      {isPending && <p className="text-sm text-muted-foreground">Loading financial profile...</p>}
      {isError && !isNotFoundError && (
        <p className="text-sm text-destructive">{(error as Error).message}</p>
      )}
      {isNotFoundError && (
        <p className="text-sm text-muted-foreground">
          No financial profile set up yet. Fill out the form below to create one.
        </p>
      )}

      <form className="grid gap-6" onSubmit={form.handleSubmit(onSubmit)}>
        <SectionBlock
          actions={(
            <button
              className="btn-primary text-sm"
              onClick={() => incomesArray.append({ description: '', amount_major: 0 })}
              type="button"
            >
              + Add Income
            </button>
          )}
          subtitle="Add all your regular monthly income sources"
          title="Income Sources"
        >
          <div className="grid gap-3">
            {incomesArray.fields.map((field, index) => {
              const fieldError =
                form.formState.errors.incomes?.[index]?.description?.message ??
                form.formState.errors.incomes?.[index]?.amount_major?.message;

              return (
                <EditableLineItem
                  amountInputProps={{
                    ...form.register(`incomes.${index}.amount_major`, { valueAsNumber: true }),
                    'aria-label': `Income amount ${index + 1}`,
                    min: 0,
                    placeholder: '0.00',
                  }}
                  deleteDisabled={incomesArray.fields.length <= 1}
                  deleteLabel={`Delete income line ${index + 1}`}
                  descriptionInputProps={{
                    ...form.register(`incomes.${index}.description`),
                    'aria-label': `Income description ${index + 1}`,
                    placeholder: 'Description',
                  }}
                  errorMessage={fieldError}
                  key={field.id}
                  onDelete={() => incomesArray.remove(index)}
                />
              );
            })}
          </div>
          {form.formState.errors.incomes?.message && (
            <p className="mt-2 text-sm text-destructive">{form.formState.errors.incomes.message}</p>
          )}
        </SectionBlock>

        <SectionBlock
          actions={(
            <button
              className="btn-primary text-sm"
              onClick={() => deductionsArray.append({ description: '', amount_major: 0 })}
              type="button"
            >
              + Add Deduction
            </button>
          )}
          subtitle="Add fixed monthly expenses not shared with the group"
          title="Deductions"
        >
          <div className="grid gap-3">
            {deductionsArray.fields.map((field, index) => {
              const fieldError =
                form.formState.errors.deductions?.[index]?.description?.message ??
                form.formState.errors.deductions?.[index]?.amount_major?.message;

              return (
                <EditableLineItem
                  amountInputProps={{
                    ...form.register(`deductions.${index}.amount_major`, { valueAsNumber: true }),
                    'aria-label': `Deduction amount ${index + 1}`,
                    min: 0,
                    placeholder: '0.00',
                  }}
                  deleteLabel={`Delete deduction line ${index + 1}`}
                  descriptionInputProps={{
                    ...form.register(`deductions.${index}.description`),
                    'aria-label': `Deduction description ${index + 1}`,
                    placeholder: 'Description',
                  }}
                  errorMessage={fieldError}
                  key={field.id}
                  onDelete={() => deductionsArray.remove(index)}
                />
              );
            })}
          </div>
        </SectionBlock>

        <SectionBlock
          actions={(
            <AsyncSaveButton
              className="text-sm"
              isError={updateMutation.isError}
              isSubmitting={updateMutation.isPending}
              isSuccess={updateMutation.isSuccess}
              label="Save Changes"
              type="submit"
            />
          )}
          title="Shareable Income Calculation"
        >
          <p className="amount-numeric text-xl font-semibold">
            <span className="text-inflow">{formatEuroFromMajor(totalIncomeMajor)}</span>
            <span className="mx-2 text-muted-foreground">-</span>
            <span className="text-destructive">{formatEuroFromMajor(totalDeductionsMajor)}</span>
            <span className="mx-2 text-muted-foreground">=</span>
            <span className="text-info">{formatEuroFromMajor(shareableIncomeMajor)}</span>
          </p>

          {updateMutation.isError && (
            <p className="mt-3 text-sm text-destructive">{(updateMutation.error as Error).message}</p>
          )}

          {updateMutation.isSuccess && (
            <p className="mt-3 text-sm text-inflow">Financial profile saved successfully.</p>
          )}
        </SectionBlock>
      </form>

      <SectionBlock title="How proportional splits work">
        <div className="grid gap-4 text-sm text-muted-foreground">
          <p>
            When you choose proportional split for an expense, each person pays based on their
            shareable income relative to the group total. This ensures fair contribution when
            incomes differ significantly between members.
          </p>
          <div className="border-t border-border pt-4">
            <h3 className="text-base font-semibold text-foreground">What counts as a deduction?</h3>
            <p className="mt-2">
              Deductions are fixed personal expenses that reduce your available income for shared
              costs. Common examples include personal loans, child support, individual insurance
              premiums, or other non-negotiable monthly obligations.
            </p>
          </div>
        </div>
      </SectionBlock>
    </PageScaffold>
  );
}
