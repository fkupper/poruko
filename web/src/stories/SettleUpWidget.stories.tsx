import type { Meta, StoryObj } from '@storybook/react-vite';
import { SettleUpWidgetView } from '../features/ledger/SettleUpWidget';

const meta = {
  title: 'Poruko/Widgets/Settle Up',
  component: SettleUpWidgetView,
  parameters: {
    layout: 'padded',
    docs: {
      description: {
        component: 'Settlement preview reflects the previous calendar month. Confirmation executes that monthly period.',
      },
    },
  },
  decorators: [(Story) => <div className="max-w-md bg-background p-4"><Story /></div>],
} satisfies Meta<typeof SettleUpWidgetView>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Loading: Story = {
  args: {
    state: { status: 'loading' },
    isConfirming: false,
    confirmError: null,
    onConfirm: () => undefined,
  },
};

export const Ready: Story = {
  args: {
    state: {
      status: 'ready',
      preview: {
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
        required_transfers: [],
      },
      latestSettlement: {
        id: 22,
        ledger_id: 1,
        period_start: '2026-02-01',
        period_end: '2026-02-28',
        executed_at: '2026-03-01T00:00:00.000Z',
      },
    },
    isConfirming: false,
    confirmError: null,
    onConfirm: () => undefined,
  },
};

export const ConfirmationRequired: Story = {
  args: {
    state: {
      status: 'confirmation_required',
      preview: {
        ledger_id: 1,
        period_start: '2026-03-01',
        period_end: '2026-03-31',
        settlement_mode: 'direct_p2p',
        summary: {
          total_shared_spend: 150000,
          pool_current_balance: 190000,
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
            from_account_id: 101,
            to_account_id: 102,
            amount: 45000,
            instruction: 'Bob transfers 45000 cents to Alice',
          },
        ],
      },
      pendingCycle: {
        id: 88,
        ledger_id: 1,
        period_start: '2026-03-01',
        period_end: '2026-03-31',
        executed_at: null,
      },
    },
    isConfirming: false,
    confirmError: null,
    onConfirm: () => undefined,
  },
};

export const ErrorState: Story = {
  args: {
    state: { status: 'error', message: 'Failed to load settlement preview' },
    isConfirming: false,
    confirmError: null,
    onConfirm: () => undefined,
  },
};

