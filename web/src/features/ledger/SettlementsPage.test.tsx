import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import { SettlementsPage } from './SettlementsPage';

let statusData: Array<{
  id: number;
  ledger_id: number;
  period_start: string;
  period_end: string;
  executed_at: string | null;
  transactions?: Array<{
    id: number;
    settlement_id: number;
    ledger_id: number;
    credit_account_id: number;
    credit_account_name?: string | null;
    debit_account_id: number;
    debit_account_name?: string | null;
    amount: number;
    type: 'settlement';
    split_rule: 'individual';
    participants: Array<{ user_id: number }>;
    description: string | null;
    date: string;
    created_at: string | null;
    updated_at: string | null;
  }>;
}> = [];
const mutateAsync = vi.fn().mockResolvedValue(undefined);

vi.mock('../../api/settlements', () => {
  return {
    useSettlementPreviewQuery: () => ({
      isPending: false,
      isError: false,
      data: {
        ledger_id: 1,
        period_start: '2026-03-01',
        period_end: '2026-03-31',
        settlement_mode: 'joint_clearinghouse',
        summary: {
          total_shared_spend: 1000,
          pool_current_balance: 2000,
        },
        user_breakdowns: [
          {
            user_id: 1,
            name: 'Alice',
            active_ratio: 0.5,
            target_liability: 50000,
            paid_out_of_pocket: 100000,
            net_balance: 50000,
          },
          {
            user_id: 2,
            name: 'Bob',
            active_ratio: 0.5,
            target_liability: 50000,
            paid_out_of_pocket: 0,
            net_balance: -50000,
          },
        ],
        required_transfers: [],
      },
    }),
    useSettlementsStatusQuery: () => ({
      isPending: false,
      isError: false,
      data: statusData,
    }),
    useConfirmSettlementCycleMutation: () => ({
      isPending: false,
      mutateAsync,
      isError: false,
      error: undefined,
    }),
  };
});

function renderWithClient(ui: React.ReactElement) {
  const client = new QueryClient();

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>);
}

describe('SettlementsPage', () => {
  it('renders preview and empty history', () => {
    statusData = [];
    renderWithClient(<SettlementsPage ledgerId={1} />);

    expect(screen.getByRole('heading', { level: 1, name: 'Settlements' })).toBeInTheDocument();
    expect(screen.getByText('Previous month preview')).toBeInTheDocument();
    expect(screen.getByText('User breakdowns')).toBeInTheDocument();
    expect(screen.getByText('Alice')).toBeInTheDocument();
    expect(screen.getByText('Bob')).toBeInTheDocument();
    expect(screen.getByText(/Owed €500\.00/)).toBeInTheDocument();
    expect(screen.getByText(/Owes €500\.00/)).toBeInTheDocument();
    expect(screen.getByText('No settlements recorded yet.')).toBeInTheDocument();
  });

  it('confirms a pending settlement cycle', () => {
    statusData = [
      {
        id: 7,
        ledger_id: 1,
        period_start: '2026-03-01',
        period_end: '2026-03-31',
        executed_at: null,
        transactions: [
          {
            id: 11,
            settlement_id: 7,
            ledger_id: 1,
            credit_account_id: 15,
            credit_account_name: 'Main Wallet',
            debit_account_id: 12,
            debit_account_name: 'Settlement Pool',
            amount: 67000,
            type: 'settlement',
            split_rule: 'individual',
            participants: [{ user_id: 1 }],
            description: 'Settlement transfer for 2026-03-31',
            date: '2026-03-31',
            created_at: null,
            updated_at: null,
          },
        ],
      },
    ];

    renderWithClient(<SettlementsPage ledgerId={1} />);
    expect(screen.getByText('Generated transactions')).toBeInTheDocument();
    expect(screen.getByText('Settlement transfer for 2026-03-31')).toBeInTheDocument();
    expect(screen.getByText('From account Main Wallet')).toBeInTheDocument();
    fireEvent.click(screen.getByText('Confirm settlement for previous month'));
    expect(mutateAsync).toHaveBeenCalledWith('2026-03-31');
  });
});

