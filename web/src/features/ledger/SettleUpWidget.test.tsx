import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import { SettleUpWidget } from './SettleUpWidget';

const mutateAsync = vi.fn().mockResolvedValue(undefined);

vi.mock('../../api/settlements', () => {
  return {
    useSettlementPreviewQuery: () => ({
      isPending: false,
      isError: false,
      error: null,
      data: {
        ledger_id: 1,
        period_start: '2026-03-01',
        period_end: '2026-03-31',
        settlement_mode: 'joint_clearinghouse',
        summary: {
          total_shared_spend: 120000,
          pool_current_balance: 240000,
        },
        user_breakdowns: [
          {
            user_id: 1,
            name: 'Bob',
            active_ratio: 0.6,
            target_liability: 83000,
            paid_out_of_pocket: 30000,
            net_balance: -53000,
          },
          {
            user_id: 2,
            name: 'Clara',
            active_ratio: 0.4,
            target_liability: 57000,
            paid_out_of_pocket: 0,
            net_balance: -57000,
          },
        ],
        required_transfers: [
          {
            from_account_id: 10,
            to_account_id: 20,
            amount: 67000,
            instruction: 'A transfer is needed.',
          },
        ],
      },
    }),
    useSettlementsStatusQuery: () => ({
      isPending: false,
      isError: false,
      error: null,
      data: [
        {
          id: 1,
          ledger_id: 1,
          period_start: '2026-03-01',
          period_end: '2026-03-31',
          executed_at: null,
        },
      ],
    }),
    useConfirmSettlementCycleMutation: () => ({
      isPending: false,
      isError: false,
      error: null,
      mutateAsync,
    }),
  };
});

function renderWithClient(ui: React.ReactElement) {
  const client = new QueryClient();

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>);
}

describe('SettleUpWidget', () => {
  it('renders transfer instructions and allows confirming a pending cycle', () => {
    renderWithClient(<SettleUpWidget ledgerId={1} today="2026-03-31" />);

    expect(screen.getByText('Settle up')).toBeInTheDocument();
    expect(screen.getByText('A transfer is needed.')).toBeInTheDocument();
    fireEvent.click(screen.getByText('Confirm monthly settlement'));
    expect(mutateAsync).toHaveBeenCalledWith('2026-03-31');
  });

  it('renders user breakdowns with liability, paid and net amounts', () => {
    renderWithClient(<SettleUpWidget ledgerId={1} today="2026-03-31" />);

    expect(screen.getByText('User breakdowns')).toBeInTheDocument();
    expect(screen.getByText('Bob')).toBeInTheDocument();
    expect(screen.getByText('Clara')).toBeInTheDocument();
    expect(screen.getByText(/Owes €530\.00/)).toBeInTheDocument();
    expect(screen.getByText(/Owes €570\.00/)).toBeInTheDocument();
  });
});

