import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { ManageAccountsPage } from './ManageAccountsPage';

const mutateAsyncMock = vi.fn();

vi.mock('../../api/accounts', () => ({
  useAccountsQuery: () => ({
    data: [
      { id: 1, name: 'Pool', type: 'pool' },
      { id: 2, name: 'Alice', type: 'personal' },
    ],
    isPending: false,
    isError: false,
    error: null,
  }),
  useCreateAccountMutation: () => ({
    isPending: false,
    mutateAsync: mutateAsyncMock,
  }),
}));

vi.mock('./AddExpenseModal', () => ({
  AddExpenseModal: () => <div data-testid="add-expense-modal" />,
}));

vi.mock('./TransactionHistoryTable', () => ({
  TransactionHistoryTable: () => <div data-testid="transaction-history" />,
}));

describe('ManageAccountsPage', () => {
  it('renders account list and submits create account', async () => {
    render(<ManageAccountsPage ledgerId={12} />);

    expect(screen.getAllByText('Pool').length).toBeGreaterThan(0);
    expect(screen.getByText('Alice')).toBeInTheDocument();
    expect(screen.getByTestId('add-expense-modal')).toBeInTheDocument();
    expect(screen.getByTestId('transaction-history')).toBeInTheDocument();

    fireEvent.change(screen.getByPlaceholderText('Account name'), {
      target: { value: 'New Shared' },
    });
    fireEvent.change(screen.getByRole('combobox'), {
      target: { value: 'pool' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Add account' }));

    await waitFor(() => {
      expect(mutateAsyncMock).toHaveBeenCalledWith({
        name: 'New Shared',
        type: 'pool',
      });
    });
  });
});

