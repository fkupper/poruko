import { render, screen } from '@testing-library/react';
import { LedgerDashboardPage } from './LedgerDashboardPage';

const navigateMock = vi.fn();

const previewState = {
  isPending: false,
  isError: false,
  error: null as unknown,
  data: {
    ledger_id: 1,
    period_start: '2026-03-01',
    period_end: '2026-03-31',
    settlement_mode: 'joint_clearinghouse' as const,
    summary: {
      total_shared_spend: 120000,
      pool_current_balance: 240000,
    },
    user_breakdowns: [
      {
        user_id: 1,
        name: 'Member A',
        active_ratio: 0.6,
        target_liability: 72000,
        paid_out_of_pocket: 5000,
        net_balance: -67000,
      },
      {
        user_id: 2,
        name: 'Member B',
        active_ratio: 0.4,
        target_liability: 48000,
        paid_out_of_pocket: 76000,
        net_balance: 28000,
      },
    ],
    required_transfers: [],
  },
};

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useNavigate: () => navigateMock,
  };
});

vi.mock('../../api/transactions', () => ({
  useTransactionsQuery: () => ({
    data: [],
    isPending: false,
    isError: false,
    error: null,
  }),
}));

vi.mock('../../api/settlements', () => ({
  useSettlementPreviewQuery: () => previewState,
}));

vi.mock('./SettleUpWidget', () => ({
  SettleUpWidget: () => <div data-testid="settle-up-widget" />,
}));

describe('LedgerDashboardPage', () => {
  it('renders member cards from settlement preview user breakdowns', () => {
    render(<LedgerDashboardPage ledgerId={1} />);

    expect(screen.getByText('Previous calendar month')).toBeInTheDocument();
    expect(screen.getByText('Member A')).toBeInTheDocument();
    expect(screen.getByText('Member B')).toBeInTheDocument();
    expect(screen.getByText('Current share: 60%')).toBeInTheDocument();
    expect(screen.getByText('Current share: 40%')).toBeInTheDocument();
    expect(screen.getByText('Owes €670.00')).toBeInTheDocument();
    expect(screen.getByText('Owed €280.00')).toBeInTheDocument();
  });

  it('renders loading state for member cards', () => {
    previewState.isPending = true;
    previewState.data = undefined;

    render(<LedgerDashboardPage ledgerId={1} />);

    expect(screen.getByText('Loading member expense shares...')).toBeInTheDocument();

    previewState.isPending = false;
    previewState.data = {
      ledger_id: 1,
      period_start: '2026-03-01',
      period_end: '2026-03-31',
      settlement_mode: 'joint_clearinghouse' as const,
      summary: {
        total_shared_spend: 120000,
        pool_current_balance: 240000,
      },
      user_breakdowns: [
        {
          user_id: 1,
          name: 'Member A',
          active_ratio: 0.6,
          target_liability: 72000,
          paid_out_of_pocket: 5000,
          net_balance: -67000,
        },
      ],
      required_transfers: [],
    };
  });
});

