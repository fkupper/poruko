import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { z } from 'zod';
import { Combobox } from '../../components/Combobox';
import { SectionBlock } from '../../components/SectionBlock';
import { PageScaffold } from '../../components/PageScaffold';
import { AsyncSaveButton } from '../../components/AsyncSaveButton';
import { useLedgersQuery } from '../../api/ledgers';
import { useUpdateCycleConfigMutation } from '../../api/settlements';

interface SpaceSettingsPageProps {
  ledgerId: number;
}

const cycleConfigSchema = z.object({
  settlement_timezone: z.string().min(1, 'Timezone is required'),
  settlement_cutoff_day: z.number().int().min(1, 'Cutoff day is required').max(31),
  settlement_cutoff_time: z
    .string()
    .min(1, 'Cutoff time is required')
    .regex(/^\d{2}:\d{2}:\d{2}$/, 'Time must be in HH:MM:SS format'),
  settlement_auto_execute_enabled: z.boolean(),
});

type CycleConfigFormValues = z.infer<typeof cycleConfigSchema>;

const supportedTimezones: string[] =
  typeof Intl !== 'undefined' && 'supportedValuesOf' in Intl
    ? // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ((Intl as any).supportedValuesOf('timeZone') as string[])
    : [];

export function SpaceSettingsPage({ ledgerId }: SpaceSettingsPageProps) {
  const { data: ledgers } = useLedgersQuery(true);
  const updateMutation = useUpdateCycleConfigMutation(ledgerId);

  const form = useForm<CycleConfigFormValues>({
    resolver: zodResolver(cycleConfigSchema),
    defaultValues: {
      settlement_timezone: 'UTC',
      settlement_cutoff_day: 1,
      settlement_cutoff_time: '00:00:00',
      settlement_auto_execute_enabled: false,
    },
  });

  const currentLedger = ledgers?.find((ledger) => ledger.id === ledgerId);

  useEffect(() => {
    if (!currentLedger) {
      return;
    }

    form.reset({
      settlement_timezone: currentLedger.settlement_timezone ?? 'UTC',
      settlement_cutoff_day: currentLedger.settlement_cutoff_day ?? 1,
      settlement_cutoff_time: currentLedger.settlement_cutoff_time ?? '00:00:00',
      settlement_auto_execute_enabled: currentLedger.settlement_auto_execute_enabled ?? false,
    });
  }, [currentLedger, form]);

  async function onSubmit(values: CycleConfigFormValues): Promise<void> {
    await updateMutation.mutateAsync(values);
  }

  return (
    <PageScaffold
      title="Space settings"
      subtitle="Control when settlements run and which month gets settled."
    >
      <SectionBlock
        title="Settlement cycle"
        subtitle="Configure monthly settlement timing for this space."
      >
        <form className="grid gap-4" onSubmit={form.handleSubmit(onSubmit)}>
          <label className="grid gap-1 text-sm">
            <span>Settlement timezone</span>
            <span className="text-xs text-muted-foreground">
              Timezone used to determine when the monthly cutoff is reached.
            </span>
            <Controller
              control={form.control}
              name="settlement_timezone"
              render={({ field }) => (
                <Combobox
                  id="settlement-timezone"
                  value={field.value}
                  onChange={(next) => field.onChange(next)}
                  options={supportedTimezones.map((timezone) => ({
                    value: timezone,
                    label: timezone,
                  }))}
                  placeholder="Select a timezone"
                  required
                />
              )}
            />
          </label>
          {form.formState.errors.settlement_timezone && (
            <p className="text-sm text-destructive">{form.formState.errors.settlement_timezone.message}</p>
          )}

          <label className="grid gap-1 text-sm">
            <span>Settlement execution day (cutoff day)</span>
            <span className="text-xs text-muted-foreground">
              Day of month when the previous calendar month becomes due for settlement.
            </span>
            <input
              className="field-input"
              type="number"
              {...form.register('settlement_cutoff_day', { valueAsNumber: true })}
            />
          </label>
          {form.formState.errors.settlement_cutoff_day && (
            <p className="text-sm text-destructive">{form.formState.errors.settlement_cutoff_day.message}</p>
          )}

          <label className="grid gap-1 text-sm">
            <span>Cutoff time (HH:MM:SS)</span>
            <span className="text-xs text-muted-foreground">
              Time of day when the settlement cutoff is applied in the selected timezone.
            </span>
            <input
              className="field-input"
              type="text"
              {...form.register('settlement_cutoff_time')}
            />
          </label>
          {form.formState.errors.settlement_cutoff_time && (
            <p className="text-sm text-destructive">{form.formState.errors.settlement_cutoff_time.message}</p>
          )}

          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              {...form.register('settlement_auto_execute_enabled')}
            />
            <span>Automatically execute settlements when the monthly cutoff is reached.</span>
          </label>

          <p className="text-xs text-muted-foreground">
            When auto-execute is enabled, the previous calendar month is executed once the cutoff is reached. Safety checks can still require explicit confirmation in the Settlements page.
          </p>

          {updateMutation.isError && (
            <p className="text-sm text-destructive">{(updateMutation.error as Error).message}</p>
          )}
          {updateMutation.isSuccess && (
            <p className="text-sm text-inflow">Space settings saved successfully.</p>
          )}

          <AsyncSaveButton
            className="text-sm"
            isError={updateMutation.isError}
            isSubmitting={updateMutation.isPending}
            isSuccess={updateMutation.isSuccess}
            label="Save settings"
            type="submit"
          />
        </form>
      </SectionBlock>

      {/* Notifications section (mocked UI only, no persistence yet) */}
      <section className="panel">
        <div className="section-header">
          <h2 className="section-title">Notifications</h2>
          <p className="section-subtitle">Choose what updates you want to receive.</p>
        </div>

        <div className="mt-4 space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-foreground">New Expense Alerts</p>
              <p className="mt-1 text-xs text-muted-foreground">
                Get notified when a household member adds an expense.
              </p>
            </div>
            <button
              type="button"
              aria-pressed="true"
              className="relative inline-flex h-5 w-9 items-center rounded-full bg-info transition-colors"
            >
              <span className="inline-block h-4 w-4 translate-x-4 rounded-full bg-background shadow transition-transform" />
            </button>
          </div>

          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-foreground">Settlement Reminders</p>
              <p className="mt-1 text-xs text-muted-foreground">
                Receive reminders when it&apos;s time to settle up.
              </p>
            </div>
            <button
              type="button"
              aria-pressed="true"
              className="relative inline-flex h-5 w-9 items-center rounded-full bg-info transition-colors"
            >
              <span className="inline-block h-4 w-4 translate-x-4 rounded-full bg-background shadow transition-transform" />
            </button>
          </div>

          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-foreground">Weekly Summary</p>
              <p className="mt-1 text-xs text-muted-foreground">
                Get a weekly digest of household spending.
              </p>
            </div>
            <button
              type="button"
              aria-pressed="false"
              className="relative inline-flex h-5 w-9 items-center rounded-full bg-surfaceStrong transition-colors"
            >
              <span className="inline-block h-4 w-4 translate-x-1 rounded-full bg-foreground/60 shadow transition-transform" />
            </button>
          </div>

          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-foreground">Email Notifications</p>
              <p className="mt-1 text-xs text-muted-foreground">
                Receive notifications via email in addition to in-app.
              </p>
            </div>
            <button
              type="button"
              aria-pressed="false"
              className="relative inline-flex h-5 w-9 items-center rounded-full bg-surfaceStrong transition-colors"
            >
              <span className="inline-block h-4 w-4 translate-x-1 rounded-full bg-foreground/60 shadow transition-transform" />
            </button>
          </div>
        </div>
      </section>
    </PageScaffold>
  );
}

