import { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { formatEuroFromCents } from '../../lib/currency';
import { PageScaffold } from '../../components/PageScaffold';
import { useSettlementPreviewQuery } from '../../api/settlements';
import { useTransactionsQuery } from '../../api/transactions';
import { SettleUpWidget } from './SettleUpWidget';

interface LedgerDashboardPageProps {
  ledgerId: number;
}

interface DashboardUserBalance {
  name: string;
  ratio: number;
  net: number; // cents
  netType: 'owes' | 'owed' | 'settled';
}

interface DashboardPool {
  balance: number;
  spent: number;
  total: number;
  daysLeft: number;
}

interface DashboardSplitDistribution {
  label: 'Proportional' | 'Equal' | 'Individual' | 'Manual';
  value: number;
}

interface DashboardFundingSource {
  name: string;
  defaultRule: string;
}

interface DashboardMock {
  hasPool: boolean;
  pool?: DashboardPool;
  splitDistribution: DashboardSplitDistribution[];
  fundingSources: DashboardFundingSource[];
}

const DASHBOARD_MOCKS: Record<number | 'default', DashboardMock> = {
  // Simple default mock; can be expanded per-ledger later.
  default: {
    hasPool: true,
    pool: { balance: 240000, spent: 60000, total: 300000, daysLeft: 12 },
    splitDistribution: [
      { label: 'Proportional', value: 75 },
      { label: 'Equal', value: 15 },
      { label: 'Individual', value: 10 },
    ],
    fundingSources: [
      { name: 'Household Joint Account', defaultRule: 'Proportional' },
      { name: 'Member A Card', defaultRule: 'Individual' },
      { name: 'Member B Card', defaultRule: 'Individual' },
    ],
  },
};

export function LedgerDashboardPage({ ledgerId }: LedgerDashboardPageProps) {
  const navigate = useNavigate();
  const { data: transactions, isPending, isError, error } = useTransactionsQuery(ledgerId);
  const today = new Date().toISOString().slice(0, 10);
  const previewQuery = useSettlementPreviewQuery(ledgerId, today);

  const mock = useMemo<DashboardMock>(() => {
    return DASHBOARD_MOCKS[ledgerId] ?? DASHBOARD_MOCKS.default;
  }, [ledgerId]);

  const recentTransactions = useMemo(() => {
    if (transactions === undefined) {
      return [];
    }

    return [...transactions]
      .sort((a, b) => (a.date > b.date ? -1 : 1))
      .slice(0, 5);
  }, [transactions]);

  const hasRecentTransactions = recentTransactions.length > 0;
  const memberBalances = useMemo<DashboardUserBalance[]>(() => {
    if (previewQuery.data === undefined) {
      return [];
    }

    return previewQuery.data.user_breakdowns.map((member) => {
      const ratio = Math.round(member.active_ratio * 1000) / 10;
      const netCents = Math.abs(member.net_balance);
      const netType: DashboardUserBalance['netType'] =
        member.net_balance < 0 ? 'owes' : member.net_balance > 0 ? 'owed' : 'settled';

      return {
        name: member.name,
        ratio,
        net: netCents,
        netType,
      };
    });
  }, [previewQuery.data]);

  return (
    <PageScaffold
      title="Overview"
      subtitle={`Space #${ledgerId}`}
    >
      <section className="panel">
        <h2 className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Ledger balance</h2>

        {mock.hasPool && mock.pool && (
          <div className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex-1">
              <div className="flex items-end justify-between">
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Joint pool health</p>
                <p className="text-xs font-semibold text-foreground">
                  {formatEuroFromCents(mock.pool.balance)} remaining
                </p>
              </div>
              <div className="mt-2 flex h-2.5 w-full overflow-hidden rounded-full bg-surfaceStrong">
                <div
                  className="bg-info"
                  style={{ width: `${(mock.pool.spent / mock.pool.total) * 100}%` }}
                />
              </div>
              <div className="mt-2 flex justify-between text-xs text-muted-foreground">
                <span>Total spent: {formatEuroFromCents(mock.pool.spent)}</span>
                <span>{mock.pool.daysLeft} days until next settlement cutoff</span>
              </div>
            </div>

            <div className="rounded-card border border-inflowSubtle bg-inflowSubtle px-4 py-3 text-center sm:w-64">
              <p className="text-xs font-medium uppercase tracking-wide text-inflow">Status</p>
              <p className="mt-1 text-xl font-semibold text-foreground">Healthy</p>
            </div>
          </div>
        )}

        <div className="mt-6">
          <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Previous calendar month</p>

          {previewQuery.isPending && (
            <p className="mt-3 text-sm text-muted-foreground">Loading member expense shares...</p>
          )}

          {previewQuery.isError && (
            <p className="mt-3 text-sm text-destructive">
              Failed to load member balances: {previewQuery.error instanceof Error ? previewQuery.error.message : String(previewQuery.error)}
            </p>
          )}

          {!previewQuery.isPending && !previewQuery.isError && memberBalances.length === 0 && (
            <p className="mt-3 text-sm text-muted-foreground">No member balance data for the previous calendar month yet.</p>
          )}

          {memberBalances.length > 0 && (
            <div className="mt-3 grid gap-4 sm:grid-cols-2">
              {memberBalances.map((balance) => (
                <div key={balance.name} className="rounded-card border border-border bg-background p-4">
                  <div className="flex items-center justify-between">
                    <p className="font-medium text-foreground">{balance.name}</p>
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                      Current share: {balance.ratio}%
                    </p>
                  </div>
                  <div className="mt-3 flex items-center justify-between">
                    <span className="text-xs font-medium text-muted-foreground">Current owed/owed money</span>
                    <span
                      className={
                        balance.netType === 'owes'
                          ? 'amount-numeric text-sm font-semibold text-destructive'
                          : balance.netType === 'owed'
                            ? 'amount-numeric text-sm font-semibold text-inflow'
                            : 'amount-numeric text-sm font-semibold text-muted-foreground'
                      }
                    >
                      {balance.netType === 'owes'
                        ? `Owes ${formatEuroFromCents(balance.net)}`
                        : balance.netType === 'owed'
                          ? `Owed ${formatEuroFromCents(balance.net)}`
                          : 'Settled'}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </section>

      <SettleUpWidget ledgerId={ledgerId} today={today} />

      <section className="panel">
        <div className="flex items-center justify-between">
          <h2 className="section-title">Recent activity</h2>
          <button
            type="button"
            onClick={() => navigate(`/ledgers/${ledgerId}/accounts`)}
            className="text-xs font-medium text-info underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background rounded-full px-2 py-1"
          >
            History
          </button>
        </div>

        {isPending && <p className="mt-3 text-sm text-muted-foreground">Loading recent transactions...</p>}
        {isError && (
          <p className="mt-3 text-sm text-destructive">
            Failed to load transactions: {error instanceof Error ? error.message : String(error)}
          </p>
        )}
        {!isPending && !isError && !hasRecentTransactions && (
          <p className="mt-3 text-sm text-muted-foreground">No transactions yet.</p>
        )}

        {hasRecentTransactions && (
          <ul className="mt-3 divide-y divide-border">
            {recentTransactions.map((transaction) => (
              <li className="flex items-center justify-between py-4" key={transaction.id}>
                <div>
                  <p className="text-sm font-medium text-foreground">
                    {transaction.description ?? 'Untitled transaction'}
                  </p>
                  <p className="mt-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    {transaction.split_rule}
                  </p>
                </div>
                <div className="text-right">
                  <p className="amount-numeric text-sm font-semibold text-foreground">
                    {formatEuroFromCents(transaction.amount)}
                  </p>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="grid gap-6 md:grid-cols-[minmax(0,2fr),minmax(0,1.5fr)]">
        <div className="panel">
          <div className="section-header">
            <h2 className="section-title">Math breakdown</h2>
            <p className="section-subtitle">Analysis of how expenses are split this period.</p>
          </div>
          <div className="mt-4 flex h-3 w-full overflow-hidden rounded-[var(--radius-pill)] bg-infoSubtle">
            {mock.splitDistribution.map((item) => (
              <div
                key={item.label}
                className={
                  item.label === 'Proportional'
                    ? 'bg-info'
                    : item.label === 'Equal'
                      ? 'bg-inflow'
                      : item.label === 'Manual'
                        ? 'bg-warning'
                        : 'bg-destructive'
                }
                style={{ width: `${item.value}%` }}
              />
            ))}
          </div>
          <dl className="mt-4 space-y-2 text-sm">
            {mock.splitDistribution.map((item) => (
              <div className="flex items-center justify-between" key={item.label}>
                <dt className="flex items-center gap-2 text-foreground">
                  <span
                    className={
                      item.label === 'Proportional'
                        ? 'h-2 w-2 rounded-full bg-info'
                        : item.label === 'Equal'
                          ? 'h-2 w-2 rounded-full bg-inflow'
                          : item.label === 'Manual'
                            ? 'h-2 w-2 rounded-full bg-warning'
                            : 'h-2 w-2 rounded-full bg-destructive'
                    }
                  />
                  <span className="font-medium">{item.label}</span>
                </dt>
                <dd className="text-xs text-muted-foreground">{item.value}%</dd>
              </div>
            ))}
          </dl>
        </div>

        <div className="panel-dark">
          <div className="flex items-center justify-between">
            <p className="text-xs font-medium uppercase tracking-wide text-background/60">Linked wallets</p>
          </div>
          <ul className="mt-4 space-y-3 text-sm">
            {mock.fundingSources.map((source) => (
              <li className="flex items-center justify-between border-b border-background/10 pb-3 last:border-b-0 last:pb-0" key={source.name}>
                <div>
                  <p className="font-medium">{source.name}</p>
                  <p className="mt-1 text-xs font-medium uppercase tracking-wide text-background/60">
                    Default: {source.defaultRule}
                  </p>
                </div>
                <span className="rounded-pill border border-background/20 px-2 py-1 text-xs font-medium uppercase tracking-wide">
                  Active
                </span>
              </li>
            ))}
          </ul>
        </div>
      </section>
    </PageScaffold>
  );
}

