import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';

import TransactionsPage from './TransactionsPage';

vi.mock('@/features/transactions/TransactionsTable/TransactionsTable', () => ({
    TransactionsTable: () => <div>Transactions table</div>,
}));

vi.mock('@/features/transactions/AddExpenseModal/AddExpenseModal', () => ({
    AddExpenseModal: ({ open }: { open: boolean }) => (open ? <div>Log New Expense</div> : null),
}));

describe('TransactionsPage', () => {
    afterEach(() => {
        cleanup();
    });

    it('opens manual expense create from the transactions page', async () => {
        const user = userEvent.setup();

        render(
            <MemoryRouter>
                <TransactionsPage />
            </MemoryRouter>,
        );

        expect(screen.getByRole('heading', { name: /transactions history/i })).toBeInTheDocument();
        expect(screen.queryByText('Log New Expense')).not.toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /log expense/i }));

        expect(screen.getByText('Log New Expense')).toBeInTheDocument();
    });
});
