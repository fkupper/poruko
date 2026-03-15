import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { AddExpenseModal } from './AddExpenseModal';

const mutateAsyncMock = vi.fn().mockResolvedValue({});

vi.mock('@tanstack/react-query', async () => {
  const actual = await vi.importActual<typeof import('@tanstack/react-query')>('@tanstack/react-query');
  return {
    ...actual,
    useMutationState: () => [],
  };
});

vi.mock('../../api/transactions', () => ({
  useCreateTransactionMutation: () => ({
    mutateAsync: mutateAsyncMock,
    isPending: false,
    isError: false,
    error: null,
  }),
}));

const mockAccounts = [
  {
    id: 10,
    ledger_id: 3,
    owner_id: null,
    type: 'pool' as const,
    name: 'Pool',
    code: null,
    base_budget: 0,
    created_at: null,
    updated_at: null,
  },
  {
    id: 11,
    ledger_id: 3,
    owner_id: 2,
    type: 'personal' as const,
    name: 'Bob',
    code: null,
    base_budget: 0,
    created_at: null,
    updated_at: null,
  },
];

const mockUsers = [
  { id: 1, name: 'Alice' },
  { id: 2, name: 'Bob' },
];

describe('AddExpenseModal', () => {
  beforeEach(() => {
    mutateAsyncMock.mockClear();
  });

  it('submits transaction payload in cents for equal split', async () => {
    render(
      <AddExpenseModal
        accounts={mockAccounts}
        ledgerId={3}
        users={mockUsers}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'New expense' }));

    fireEvent.change(screen.getByLabelText('Source account'), { target: { value: '10' } });
    fireEvent.change(screen.getByLabelText('Destination account'), { target: { value: '11' } });
    fireEvent.change(screen.getByLabelText('Amount (major currency)'), { target: { value: '12.34' } });
    fireEvent.change(screen.getByLabelText('Description'), { target: { value: 'Lunch' } });
    fireEvent.change(screen.getByLabelText('Date'), { target: { value: '2026-03-10' } });

    const checkboxes = screen.getAllByRole('checkbox');
    fireEvent.click(checkboxes[0]);
    fireEvent.click(checkboxes[1]);

    fireEvent.click(screen.getByRole('button', { name: 'Save expense' }));

    await waitFor(() => {
      expect(mutateAsyncMock).toHaveBeenCalledWith({
        credit_account_id: 10,
        debit_account_id: 11,
        amount: 1234,
        description: 'Lunch',
        date: '2026-03-10',
        split_rule: 'equal',
        participants: [{ user_id: 1 }, { user_id: 2 }],
        type: 'manual',
      });
    });
  });

  it('submits with null participants when none selected', async () => {
    render(
      <AddExpenseModal
        accounts={mockAccounts}
        ledgerId={3}
        users={mockUsers}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'New expense' }));

    fireEvent.change(screen.getByLabelText('Source account'), { target: { value: '10' } });
    fireEvent.change(screen.getByLabelText('Destination account'), { target: { value: '11' } });
    fireEvent.change(screen.getByLabelText('Amount (major currency)'), { target: { value: '50.00' } });
    fireEvent.change(screen.getByLabelText('Date'), { target: { value: '2026-03-12' } });

    fireEvent.click(screen.getByRole('button', { name: 'Save expense' }));

    await waitFor(() => {
      expect(mutateAsyncMock).toHaveBeenCalledWith({
        credit_account_id: 10,
        debit_account_id: 11,
        amount: 5000,
        description: '',
        date: '2026-03-12',
        split_rule: 'equal',
        participants: null,
        type: 'manual',
      });
    });
  });

  it('submits transaction payload for proportional split', async () => {
    render(
      <AddExpenseModal
        accounts={[
          {
            id: 10,
            ledger_id: 3,
            owner_id: 1,
            type: 'personal',
            name: 'Alice',
            code: null,
            base_budget: 0,
            created_at: null,
            updated_at: null,
          },
          {
            id: 11,
            ledger_id: 3,
            owner_id: 2,
            type: 'personal',
            name: 'Bob',
            code: null,
            base_budget: 0,
            created_at: null,
            updated_at: null,
          },
        ]}
        ledgerId={3}
        users={mockUsers}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'New expense' }));

    fireEvent.change(screen.getByLabelText('Source account'), { target: { value: '10' } });
    fireEvent.change(screen.getByLabelText('Destination account'), { target: { value: '11' } });
    fireEvent.change(screen.getByLabelText('Amount (major currency)'), { target: { value: '50.00' } });
    fireEvent.change(screen.getByLabelText('Description'), { target: { value: 'Groceries' } });
    fireEvent.change(screen.getByLabelText('Date'), { target: { value: '2026-03-12' } });
    fireEvent.change(screen.getByLabelText('Split rule'), { target: { value: 'proportional' } });

    const checkboxes = screen.getAllByRole('checkbox');
    fireEvent.click(checkboxes[0]);
    fireEvent.click(checkboxes[1]);

    fireEvent.click(screen.getByRole('button', { name: 'Save expense' }));

    await waitFor(() => {
      expect(mutateAsyncMock).toHaveBeenCalledWith({
        credit_account_id: 10,
        debit_account_id: 11,
        amount: 5000,
        description: 'Groceries',
        date: '2026-03-12',
        split_rule: 'proportional',
        participants: [{ user_id: 1 }, { user_id: 2 }],
        type: 'manual',
      });
    });
  });

  it('submits manual split with share weights', async () => {
    render(
      <AddExpenseModal
        accounts={mockAccounts}
        ledgerId={3}
        users={mockUsers}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'New expense' }));

    fireEvent.change(screen.getByLabelText('Source account'), { target: { value: '10' } });
    fireEvent.change(screen.getByLabelText('Destination account'), { target: { value: '11' } });
    fireEvent.change(screen.getByLabelText('Amount (major currency)'), { target: { value: '100.00' } });
    fireEvent.change(screen.getByLabelText('Date'), { target: { value: '2026-03-13' } });
    fireEvent.change(screen.getByLabelText('Split rule'), { target: { value: 'manual' } });

    const checkboxes = screen.getAllByRole('checkbox');
    fireEvent.click(checkboxes[0]);
    fireEvent.click(checkboxes[1]);

    const shareInputs = screen.getAllByPlaceholderText('Share weight');
    fireEvent.change(shareInputs[0], { target: { value: '60' } });
    fireEvent.change(shareInputs[1], { target: { value: '40' } });

    fireEvent.click(screen.getByRole('button', { name: 'Save expense' }));

    await waitFor(() => {
      expect(mutateAsyncMock).toHaveBeenCalledWith({
        credit_account_id: 10,
        debit_account_id: 11,
        amount: 10000,
        description: '',
        date: '2026-03-13',
        split_rule: 'manual',
        participants: [
          { user_id: 1, share: 60 },
          { user_id: 2, share: 40 },
        ],
        type: 'manual',
      });
    });
  });

  it('blocks manual split when share weight is missing', async () => {
    render(
      <AddExpenseModal
        accounts={mockAccounts}
        ledgerId={3}
        users={mockUsers}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'New expense' }));

    fireEvent.change(screen.getByLabelText('Source account'), { target: { value: '10' } });
    fireEvent.change(screen.getByLabelText('Destination account'), { target: { value: '11' } });
    fireEvent.change(screen.getByLabelText('Amount (major currency)'), { target: { value: '100.00' } });
    fireEvent.change(screen.getByLabelText('Date'), { target: { value: '2026-03-13' } });
    fireEvent.change(screen.getByLabelText('Split rule'), { target: { value: 'manual' } });

    const checkboxes = screen.getAllByRole('checkbox');
    fireEvent.click(checkboxes[0]);
    fireEvent.click(checkboxes[1]);

    const shareInputs = screen.getAllByPlaceholderText('Share weight');
    fireEvent.change(shareInputs[0], { target: { value: '60' } });

    fireEvent.click(screen.getByRole('button', { name: 'Save expense' }));

    await waitFor(() => {
      expect(screen.getByText('Every participant requires a share weight greater than 0 for manual split.')).toBeInTheDocument();
    });
    expect(mutateAsyncMock).not.toHaveBeenCalled();
  });
});
