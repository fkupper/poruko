import { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTransactionsQuery } from '../../api/transactions';

interface LedgerDashboardPageProps {
  ledgerId: number;
}

interface DashboardUserBalance {
  name: string;
  ratio: number;
  net: number;
  netType: 'owes' | 'owed';
}

interface DashboardPool {
  balance: number;
  spent: number;
  total: number;
  daysLeft: number;
}

interface DashboardSplitDistribution {
  label: 'Proportional' | 'Equal' | 'Individual';
  value: number;
}

interface DashboardFundingSource {
  name: string;
  defaultRule: string;
}

interface DashboardMock {
  hasPool: boolean;
  balances: DashboardUserBalance[];
  pool?: DashboardPool;
  splitDistribution: DashboardSplitDistribution[];
  fundingSources: DashboardFundingSource[];
}

const DASHBOARD_MOCKS: Record<number | 'default', DashboardMock> = {
  // Simple default mock; can be expanded per-ledger later.
  default: {
    hasPool: true,
    balances: [
      { name: 'Member A', ratio: 60, net: 670, netType: 'owes' },
      { name: 'Member B', ratio: 40, net: 280, netType: 'owes' },
    ],
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

function centsToMajor(amount: number): string {
  return (amount / 100).toFixed(2);
}

export function LedgerDashboardPage({ ledgerId }: LedgerDashboardPageProps) {
  const navigate = useNavigate();
  const { data: transactions, isPending, isError, error } = useTransactionsQuery(ledgerId);

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

  return (
    <main className="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-8">
      <header className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <h1 className="text-2xl font-semibold">Overview</h1>
          <p className="mt-1 text-sm text-muted-foreground">Space #{ledgerId}</p>
        </div>
      </header>

      <section className="panel">
        <h2 className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Ledger balance</h2>

        {mock.hasPool && mock.pool && (
          <div className="mt-4 grid gap-4 sm:grid-cols-[minmax(0,2fr),minmax(0,1fr)] sm:items-center">
            <div>
              <div className="flex items-end justify-between">
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Joint pool health</p>
                <p className="text-xs font-semibold text-foreground">
                  €{centsToMajor(mock.pool.balance)} remaining
                </p>
              </div>
              <div className="mt-2 flex h-2.5 w-full overflow-hidden rounded-full bg-surfaceStrong">
                <div
                  className="bg-info"
                  style={{ width: `${(mock.pool.spent / mock.pool.total) * 100}%` }}
                />
              </div>
              <div className="mt-2 flex justify-between text-xs text-muted-foreground">
                <span>Total spent: €{centsToMajor(mock.pool.spent)}</span>
                <span>{mock.pool.daysLeft} days until next cycle</span>
              </div>
            </div>

            <div className="rounded-card border border-inflowSubtle bg-inflowSubtle px-4 py-3 text-center">
              <p className="text-xs font-medium uppercase tracking-wide text-inflow">Status</p>
              <p className="mt-1 text-xl font-semibold text-foreground">Healthy</p>
            </div>
          </div>
        )}

        <div className="mt-6 grid gap-4 sm:grid-cols-2">
          {mock.balances.map((balance) => (
            <div key={balance.name} className="rounded-card border border-border bg-background p-4">
              <div className="flex items-center justify-between">
                <p className="font-medium text-foreground">{balance.name}</p>
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                  Share: {balance.ratio}%
                </p>
              </div>
              <div className="mt-3 flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">
                  Net settlement{' '}
                  <span className="sr-only">
                    {balance.netType === 'owes' ? 'user owes this amount' : 'user is owed this amount'}
                  </span>
                </span>
                <span
                  className={
                    balance.netType === 'owes'
                      ? 'amount-numeric text-sm font-semibold text-destructive'
                      : 'amount-numeric text-sm font-semibold text-inflow'
                  }
                >
                  {balance.netType === 'owes' ? 'Owes ' : 'Owed '}
                  €{centsToMajor(balance.net * 100)}
                </span>
              </div>
            </div>
          ))}
        </div>
      </section>

      <section className="panel">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-semibold">Recent activity</h2>
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
                    €{centsToMajor(transaction.amount)}
                  </p>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="grid gap-6 md:grid-cols-[minmax(0,2fr),minmax(0,1.5fr)]">
        <div className="panel">
          <h2 className="text-sm font-semibold text-foreground">Math breakdown</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Analysis of how expenses are split this period.
          </p>
          <div className="mt-4 flex h-3 w-full overflow-hidden rounded-[var(--radius-pill)] bg-infoSubtle">
            {mock.splitDistribution.map((item) => (
              <div
                key={item.label}
                className={
                  item.label === 'Proportional'
                    ? 'bg-info'
                    : item.label === 'Equal'
                      ? 'bg-inflow'
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
    </main>
  );
}

