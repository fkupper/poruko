import { useMemo } from 'react';
import {
  useConfirmSettlementCycleMutation,
  useSettlementPreviewQuery,
  useSettlementsStatusQuery,
} from '../../api/settlements';
import { formatEuroFromCents } from '../../lib/currency';
import { buildSettleUpViewModel, type SettleUpViewModel } from './settle-up-view-model';

interface SettleUpWidgetProps {
  ledgerId: number;
  today: string;
}

export function SettleUpWidget({ ledgerId, today }: SettleUpWidgetProps) {
  const previewQuery = useSettlementPreviewQuery(ledgerId, today);
  const statusQuery = useSettlementsStatusQuery(ledgerId);
  const confirmMutation = useConfirmSettlementCycleMutation(ledgerId);

  const state = useMemo<SettleUpViewModel>(() => {
    return buildSettleUpViewModel({
      isPreviewPending: previewQuery.isPending,
      isStatusPending: statusQuery.isPending,
      previewError: previewQuery.isError ? (previewQuery.error as Error) : null,
      statusError: statusQuery.isError ? (statusQuery.error as Error) : null,
      preview: previewQuery.data,
      settlements: statusQuery.data,
    });
  }, [
    previewQuery.isPending,
    statusQuery.isPending,
    previewQuery.isError,
    statusQuery.isError,
    previewQuery.error,
    statusQuery.error,
    previewQuery.data,
    statusQuery.data,
  ]);

  return (
    <SettleUpWidgetView
      state={state}
      isConfirming={confirmMutation.isPending}
      confirmError={confirmMutation.isError ? (confirmMutation.error as Error) : null}
      onConfirm={(cycle) => void confirmMutation.mutateAsync(cycle)}
    />
  );
}

interface SettleUpWidgetViewProps {
  state: SettleUpViewModel;
  isConfirming: boolean;
  confirmError: Error | null;
  onConfirm: (cycle: string) => void;
}

export function SettleUpWidgetView({ state, isConfirming, confirmError, onConfirm }: SettleUpWidgetViewProps) {
  if (state.status === 'loading') {
    return (
      <section className="panel">
        <div className="section-header">
          <h2 className="section-title">Settle up</h2>
          <p className="section-subtitle">Calculating who owes what for the previous calendar month…</p>
        </div>
      </section>
    );
  }

  if (state.status === 'error') {
    return (
      <section className="panel">
        <div className="section-header">
          <h2 className="section-title">Settle up</h2>
          <p className="section-subtitle text-destructive">Failed to load settlement preview: {state.message}</p>
        </div>
      </section>
    );
  }

  const preview = state.preview;
  const pendingCycle = state.status === 'confirmation_required' ? state.pendingCycle : null;
  const latestSettlement = state.status === 'ready' ? state.latestSettlement : null;

  return (
    <section className="panel">
      <div className="section-header">
        <h2 className="section-title">Settle up</h2>
        <p className="section-subtitle">True-up summary for the previous calendar month, with transfer guidance.</p>
      </div>

      <dl className="mt-3 grid gap-2 text-sm">
        <div className="flex items-center justify-between">
          <dt className="text-muted-foreground">Period</dt>
          <dd className="text-foreground">
            {preview.period_start} - {preview.period_end}
          </dd>
        </div>
        <div className="flex items-center justify-between">
          <dt className="text-muted-foreground">Mode</dt>
          <dd className="text-foreground">{preview.settlement_mode}</dd>
        </div>
        <div className="flex items-center justify-between">
          <dt className="text-muted-foreground">Shared spend</dt>
          <dd className="amount-numeric text-foreground">{formatEuroFromCents(preview.summary.total_shared_spend)}</dd>
        </div>
      </dl>

      {preview.user_breakdowns.length > 0 && (
        <div className="mt-3 space-y-2">
          <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">User breakdowns</p>
          {preview.user_breakdowns.map((user) => (
            <div key={user.user_id} className="text-sm">
              <p className="font-medium text-foreground">{user.name}</p>
              <p className="text-xs text-muted-foreground">
                Liability: {formatEuroFromCents(user.target_liability)} | Paid: {formatEuroFromCents(user.paid_out_of_pocket)} | Net:{' '}
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

      {preview.required_transfers.length > 0 ? (
        <ul className="mt-3 space-y-2 text-xs text-muted-foreground">
          {preview.required_transfers.map((transfer) => (
            <li key={`${transfer.from_account_id}-${transfer.to_account_id}-${transfer.amount}`}>
              {transfer.instruction}
            </li>
          ))}
        </ul>
      ) : (
        <p className="mt-3 text-xs text-muted-foreground">No transfers required for the previous calendar month.</p>
      )}

      {latestSettlement?.executed_at && (
        <p className="mt-3 text-xs text-muted-foreground">Last executed at {latestSettlement.executed_at}.</p>
      )}

      {pendingCycle && (
        <div className="mt-3 rounded-card border border-border bg-surface p-3">
          <p className="text-sm text-foreground">Confirmation required before this monthly settlement can execute.</p>
          <button
            type="button"
            className="btn-primary mt-2 text-xs"
            disabled={isConfirming}
            onClick={() => onConfirm(pendingCycle.period_end)}
          >
            {isConfirming ? 'Confirming...' : 'Confirm monthly settlement'}
          </button>
          {confirmError && <p className="mt-2 text-xs text-destructive">{confirmError.message}</p>}
        </div>
      )}
    </section>
  );
}

