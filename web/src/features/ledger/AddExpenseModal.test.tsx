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

describe('AddExpenseModal', () => {
  beforeEach(() => {
    mutateAsyncMock.mockClear();
  });

  it('submits transaction payload in cents for equal split', async () => {
    render(
      <AddExpenseModal
        accounts={[
          {
            id: 10,
            ledger_id: 3,
            owner_id: null,
            type: 'pool',
            name: 'Pool',
            code: null,
            created_at: null,
            updated_at: null,
          },
          {
            id: 11,
            ledger_id: 3,
            owner_id: null,
            type: 'personal',
            name: 'Bob',
            code: null,
            created_at: null,
            updated_at: null,
          },
        ]}
        ledgerId={3}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'New expense' }));

    fireEvent.change(screen.getByLabelText('Payer account'), { target: { value: '10' } });
    fireEvent.change(screen.getByLabelText('Amount (major currency)'), { target: { value: '12.34' } });
    fireEvent.change(screen.getByLabelText('Description'), { target: { value: 'Lunch' } });
    fireEvent.change(screen.getByLabelText('Date'), { target: { value: '2026-03-10' } });

    const checkboxes = screen.getAllByRole('checkbox');
    fireEvent.click(checkboxes[0]);
    fireEvent.click(checkboxes[1]);

    fireEvent.click(screen.getByRole('button', { name: 'Save expense' }));

    await waitFor(() => {
      expect(mutateAsyncMock).toHaveBeenCalledWith({
        payer_account_id: 10,
        amount: 1234,
        description: 'Lunch',
        date: '2026-03-10',
        split_rule: 'equal',
        participants: [{ account_id: 10 }, { account_id: 11 }],
        type: 'manual',
      });
    });
  });

  it('blocks individual split when a participant amount is missing', async () => {
    render(
      <AddExpenseModal
        accounts={[
          {
            id: 10,
            ledger_id: 3,
            owner_id: null,
            type: 'pool',
            name: 'Pool',
            code: null,
            created_at: null,
            updated_at: null,
          },
          {
            id: 11,
            ledger_id: 3,
            owner_id: null,
            type: 'personal',
            name: 'Bob',
            code: null,
            created_at: null,
            updated_at: null,
          },
        ]}
        ledgerId={3}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'New expense' }));

    fireEvent.change(screen.getByLabelText('Payer account'), { target: { value: '10' } });
    fireEvent.change(screen.getByLabelText('Amount (major currency)'), { target: { value: '12.34' } });
    fireEvent.change(screen.getByLabelText('Date'), { target: { value: '2026-03-10' } });
    fireEvent.change(screen.getByLabelText('Split rule'), { target: { value: 'individual' } });

    const checkboxes = screen.getAllByRole('checkbox');
    fireEvent.click(checkboxes[0]);
    fireEvent.click(checkboxes[1]);

    const amountInputs = screen.getAllByPlaceholderText('Amount');
    fireEvent.change(amountInputs[0], { target: { value: '12.34' } });

    fireEvent.click(screen.getByRole('button', { name: 'Save expense' }));

    await waitFor(() => {
      expect(screen.getByText('Every participant requires an amount for individual split.')).toBeInTheDocument();
    });
    expect(mutateAsyncMock).not.toHaveBeenCalled();
  });

  it('blocks individual split when participant total differs from amount', async () => {
    render(
      <AddExpenseModal
        accounts={[
          {
            id: 10,
            ledger_id: 3,
            owner_id: null,
            type: 'pool',
            name: 'Pool',
            code: null,
            created_at: null,
            updated_at: null,
          },
          {
            id: 11,
            ledger_id: 3,
            owner_id: null,
            type: 'personal',
            name: 'Bob',
            code: null,
            created_at: null,
            updated_at: null,
          },
        ]}
        ledgerId={3}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'New expense' }));

    fireEvent.change(screen.getByLabelText('Payer account'), { target: { value: '10' } });
    fireEvent.change(screen.getByLabelText('Amount (major currency)'), { target: { value: '12.34' } });
    fireEvent.change(screen.getByLabelText('Date'), { target: { value: '2026-03-10' } });
    fireEvent.change(screen.getByLabelText('Split rule'), { target: { value: 'individual' } });

    const checkboxes = screen.getAllByRole('checkbox');
    fireEvent.click(checkboxes[0]);
    fireEvent.click(checkboxes[1]);

    const amountInputs = screen.getAllByPlaceholderText('Amount');
    fireEvent.change(amountInputs[0], { target: { value: '6.00' } });
    fireEvent.change(amountInputs[1], { target: { value: '5.00' } });

    fireEvent.click(screen.getByRole('button', { name: 'Save expense' }));

    await waitFor(() => {
      expect(screen.getByText('Individual participant amounts must equal the total amount.')).toBeInTheDocument();
    });
    expect(mutateAsyncMock).not.toHaveBeenCalled();
  });

  it('submits transaction payload for proportional split without individual amounts', async () => {
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
            created_at: null,
            updated_at: null,
          },
        ]}
        ledgerId={3}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'New expense' }));

    fireEvent.change(screen.getByLabelText('Payer account'), { target: { value: '10' } });
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
        payer_account_id: 10,
        amount: 5000,
        description: 'Groceries',
        date: '2026-03-12',
        split_rule: 'proportional',
        participants: [{ account_id: 10 }, { account_id: 11 }],
        type: 'manual',
      });
    });
  });
});

