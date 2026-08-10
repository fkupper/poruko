import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useLedgerStore } from '@/stores/ledgerStore';

import { AddExpenseModal } from './AddExpenseModal';

const createTransactionMock = vi.fn();
const updateTransactionMock = vi.fn();
const fetchLedgerMembersMock = vi.fn();
const fetchAccountsMock = vi.fn();
const fetchLedgersMock = vi.fn();

vi.mock('@/api/transactions', () => ({
    createTransaction: (...args: unknown[]) => createTransactionMock(...args),
    updateTransaction: (...args: unknown[]) => updateTransactionMock(...args),
}));

vi.mock('@/api/members', () => ({
    fetchLedgerMembers: (...args: unknown[]) => fetchLedgerMembersMock(...args),
}));

vi.mock('@/api/accounts', () => ({
    fetchAccounts: (...args: unknown[]) => fetchAccountsMock(...args),
}));

vi.mock('@/api/ledgers', () => ({
    fetchLedgers: (...args: unknown[]) => fetchLedgersMock(...args),
}));

vi.mock('@/hooks/use-ledger-currency', () => ({
    useLedgerCurrencySymbol: () => '€',
}));

function createTestQueryClient() {
    return new QueryClient({
        defaultOptions: {
            queries: { retry: false },
            mutations: { retry: false },
        },
    });
}

function renderModal() {
    const queryClient = createTestQueryClient();
    return render(
        <QueryClientProvider client={queryClient}>
            <AddExpenseModal open onOpenChange={vi.fn()} />
        </QueryClientProvider>,
    );
}

describe('AddExpenseModal', () => {
    beforeEach(() => {
        createTransactionMock.mockReset();
        updateTransactionMock.mockReset();
        fetchLedgerMembersMock.mockReset();
        fetchAccountsMock.mockReset();
        fetchLedgersMock.mockReset();
        useLedgerStore.setState({ activeLedgerId: 1 });

        fetchLedgersMock.mockResolvedValue([
            {
                id: 1,
                name: 'Home',
                my_preferences: {
                    default_payment_account_id: 10,
                    default_expense_account_id: 20,
                },
            },
        ]);
        fetchAccountsMock.mockResolvedValue([
            { id: 10, name: 'Alice Checking', type: 'personal', owner_id: 1 },
            { id: 20, name: 'Shared Expense', type: 'space_expense', owner_id: null },
        ]);
        fetchLedgerMembersMock.mockResolvedValue([
            { id: 1, name: 'Alice', shareable_income: 60000, is_active: true },
            { id: 2, name: 'Bob', shareable_income: 40000, is_active: true },
        ]);
        createTransactionMock.mockResolvedValue({ id: 99 });
    });

    afterEach(() => {
        cleanup();
    });

    it('posts individual with exactly one participant', async () => {
        const user = userEvent.setup();
        renderModal();

        await screen.findByText('Alice');

        await user.type(screen.getByLabelText(/description/i), 'Solo coffee');
        await user.type(screen.getByLabelText(/^amount$/i), '12.50');
        await user.click(screen.getByRole('radio', { name: /Individual/i }));

        await waitFor(() => {
            expect(screen.getByRole('radio', { name: /Assign to Alice/i })).toBeChecked();
        });

        await user.click(screen.getByRole('radio', { name: /Assign to Bob/i }));
        await user.click(screen.getByRole('button', { name: /save expense/i }));

        await waitFor(() => {
            expect(createTransactionMock).toHaveBeenCalled();
        });

        const [, payload] = createTransactionMock.mock.calls[0];
        expect(payload.split_rule).toBe('individual');
        expect(payload.participants).toEqual([{ user_id: 2 }]);
        expect(payload.amount).toBe(1250);
    });

    it('posts manual with weight shares', async () => {
        const user = userEvent.setup();
        renderModal();

        await screen.findByText('Alice');

        await user.type(screen.getByLabelText(/description/i), 'Uneven dinner');
        await user.type(screen.getByLabelText(/^amount$/i), '10.00');
        await user.click(screen.getByRole('radio', { name: /^Manual$/i }));

        await waitFor(() => {
            expect(screen.getByLabelText(/Weight for Alice/i)).toBeInTheDocument();
        });

        const aliceWeight = screen.getByLabelText(/Weight for Alice/i);
        await user.clear(aliceWeight);
        await user.type(aliceWeight, '1');
        const bobWeight = screen.getByLabelText(/Weight for Bob/i);
        await user.clear(bobWeight);
        await user.type(bobWeight, '2');

        await user.click(screen.getByRole('button', { name: /save expense/i }));

        await waitFor(() => {
            expect(createTransactionMock).toHaveBeenCalled();
        });

        const [, payload] = createTransactionMock.mock.calls[0];
        expect(payload.split_rule).toBe('manual');
        expect(payload.participants).toEqual([
            { user_id: 1, share: 1 },
            { user_id: 2, share: 2 },
        ]);
    });

    it('blocks submit when manual has no selected participants', async () => {
        const user = userEvent.setup();
        renderModal();

        await screen.findByText('Alice');

        await user.type(screen.getByLabelText(/^amount$/i), '10.00');
        await user.click(screen.getByRole('radio', { name: /^Manual$/i }));

        await waitFor(() => {
            expect(screen.getByLabelText(/Include Alice/i)).toBeInTheDocument();
        });

        await user.click(screen.getByRole('checkbox', { name: /Include Alice/i }));
        await user.click(screen.getByRole('checkbox', { name: /Include Bob/i }));

        expect(screen.getByRole('button', { name: /save expense/i })).toBeDisabled();
        expect(
            screen.getByText(/Include at least one participant with a weight greater than zero/i),
        ).toBeInTheDocument();
        expect(createTransactionMock).not.toHaveBeenCalled();
    });

    it('shows plain-language helper for the selected split rule', async () => {
        const user = userEvent.setup();
        renderModal();

        await screen.findByText('Alice');

        expect(screen.getByText(/By shareable income/i)).toBeInTheDocument();

        await user.click(screen.getByRole('radio', { name: /^Manual$/i }));
        expect(screen.getByText(/Custom ratios \(weights\)/i)).toBeInTheDocument();
        expect(screen.getByText(/Weights are ratios/i)).toBeInTheDocument();
    });
});
