import { useMemo } from 'react';
import { useConfirmSettlementCycleMutation, useSettlementPreviewQuery, useSettlementsStatusQuery } from '../../api/settlements';
import type { SettlementUserBreakdown } from '../../api/types';
import { formatEuroFromCents } from '../../lib/currency';
import { PageScaffold } from '../../components/PageScaffold';
import { SectionBlock } from '../../components/SectionBlock';

interface SettlementsPageProps {
  ledgerId: number;
}

type SettlementsViewState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready' };

export function SettlementsPage({ ledgerId }: SettlementsPageProps) {
  const today = new Date().toISOString().slice(0, 10);
  const previewQuery = useSettlementPreviewQuery(ledgerId, today);
  const statusQuery = useSettlementsStatusQuery(ledgerId);
  const confirmMutation = useConfirmSettlementCycleMutation(ledgerId);

  const state: SettlementsViewState = useMemo(() => {
    if (previewQuery.isPending || statusQuery.isPending) {
      return { status: 'loading' };
    }

    if (previewQuery.isError || statusQuery.isError) {
      const error = (previewQuery.error ?? statusQuery.error) as Error;
      return { status: 'error', message: error.message };
    }

    return { status: 'ready' };
  }, [previewQuery.isPending, statusQuery.isPending, previewQuery.isError, statusQuery.isError, previewQuery.error, statusQuery.error]);

  const settlements = statusQuery.data ?? [];
  const latest = settlements[0];

  function handleConfirm(): void {
    if (latest === undefined) {
      return;
    }
    void confirmMutation.mutateAsync(latest.period_end);
  }

  return (
    <PageScaffold
      title="Settlements"
      subtitle={`History and status for space #${ledgerId}.`}
      className="max-w-5xl"
    >
      <SectionBlock
        title="Settlements"
        subtitle={`History and status for space #${ledgerId}.`}
      >
        {state.status === 'loading' && (
          <p className="mt-3 text-sm text-muted-foreground">Loading settlement preview and history…</p>
        )}

        {state.status === 'error' && (
          <p className="mt-3 text-sm text-destructive">Failed to load settlements: {state.message}</p>
        )}

        {state.status === 'ready' && previewQuery.data && (
          <div className="mt-4 rounded-card border border-border bg-background p-3">
            <h2 className="text-sm font-semibold text-foreground">Previous month preview</h2>
            <dl className="mt-2 grid gap-2 text-sm">
              <div className="flex items-center justify-between">
                <dt className="text-muted-foreground">Period</dt>
                <dd className="text-foreground">
                  {previewQuery.data.period_start} – {previewQuery.data.period_end}
                </dd>
              </div>
              <div className="flex items-center justify-between">
                <dt className="text-muted-foreground">Mode</dt>
                <dd className="text-foreground">{previewQuery.data.settlement_mode}</dd>
              </div>
              <div className="flex items-center justify-between">
                <dt className="text-muted-foreground">Shared spend</dt>
                <dd className="amount-numeric text-foreground">
                  {formatEuroFromCents(previewQuery.data.summary.total_shared_spend)}
                </dd>
              </div>
            </dl>

            {previewQuery.data.user_breakdowns.length > 0 && (
              <div className="mt-3 space-y-2">
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">User breakdowns</p>
                {previewQuery.data.user_breakdowns.map((user: SettlementUserBreakdown) => (
                  <div key={user.user_id} className="text-sm">
                    <p className="font-medium text-foreground">{user.name}</p>
                    <p className="text-xs text-muted-foreground">
                      Liability: {formatEuroFromCents(user.target_liability)} | Paid:{' '}
                      {formatEuroFromCents(user.paid_out_of_pocket)} | Net:{' '}
                      <span
                        className={
                          user.net_balance < 0
                            ? 'text-destructive'
                            : user.net_balance > 0
                              ? 'text-inflow'
                              : 'text-muted-foreground'
                        }
                      >
                        {user.net_balance < 0
                          ? `Owes ${formatEuroFromCents(Math.abs(user.net_balance))}`
                          : user.net_balance > 0
                            ? `Owed ${formatEuroFromCents(user.net_balance)}`
                            : 'Settled'}
                      </span>
                    </p>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </SectionBlock>

      <SectionBlock
        title="Recent settlements"
        className="mt-6"
      >

        {state.status === 'ready' && settlements.length === 0 && (
          <p className="mt-3 text-sm text-muted-foreground">No settlements recorded yet.</p>
        )}

        {state.status === 'ready' && settlements.length > 0 && (
          <ul className="mt-3 divide-y divide-border text-sm">
            {settlements.map((item) => (
              <li className="flex items-center justify-between py-3" key={item.id}>
                <div className="w-full">
                  <p className="font-medium text-foreground">
                    {item.period_start} – {item.period_end}
                  </p>
                  <p className="mt-1 text-xs text-muted-foreground">
                    {item.executed_at ? `Executed at ${item.executed_at}` : 'Pending execution at configured cutoff'}
                  </p>
                  {item.transactions && item.transactions.length > 0 && (
                    <div className="mt-3 rounded-card border border-border bg-background p-3">
                      <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Generated transactions
                      </p>
                      <ul className="mt-2 space-y-2">
                        {item.transactions.map((transaction) => (
                          <li key={transaction.id} className="rounded-card border border-border px-2 py-2">
                            <p className="text-sm font-medium text-foreground">
                              {transaction.description ?? 'Settlement transfer'}
                            </p>
                            <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                              <span>Date: {transaction.date}</span>
                              <span>Amount: {formatEuroFromCents(transaction.amount)}</span>
                              <span>
                                From account {transaction.credit_account_name ?? `#${transaction.credit_account_id}`}
                              </span>
                            </div>
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </SectionBlock>

      {state.status === 'ready' && latest && !latest.executed_at && (
        <SectionBlock
          title="Confirmation required"
          subtitle="This monthly settlement requires explicit confirmation before execution. Review the previous month preview, then confirm when ready."
          className="mt-6"
        >
          <button
            className="btn-primary text-sm"
            disabled={confirmMutation.isPending}
            onClick={handleConfirm}
            type="button"
          >
            {confirmMutation.isPending ? 'Confirming…' : 'Confirm settlement for previous month'}
          </button>
          {confirmMutation.isError && (
            <p className="mt-2 text-sm text-destructive">{(confirmMutation.error as Error).message}</p>
          )}
        </SectionBlock>
      )}
    </PageScaffold>
  );
}

