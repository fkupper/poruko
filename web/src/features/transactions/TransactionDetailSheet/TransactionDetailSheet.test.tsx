import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useLedgerStore } from '@/stores/ledgerStore';

import { TransactionDetailSheet } from './TransactionDetailSheet';

const fetchTransactionMock = vi.fn();

vi.mock('@/api/transactions', () => ({
    fetchTransaction: (...args: unknown[]) => fetchTransactionMock(...args),
}));

vi.mock('@/hooks/use-ledger-currency', () => ({
    useLedgerCurrencySymbol: () => '€',
}));

function renderSheet() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: { retry: false },
        },
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <TransactionDetailSheet open onOpenChange={vi.fn()} transactionId={42} />
        </QueryClientProvider>,
    );
}

describe('TransactionDetailSheet', () => {
    beforeEach(() => {
        fetchTransactionMock.mockReset();
        useLedgerStore.setState({ activeLedgerId: 7 });
    });

    afterEach(() => {
        cleanup();
    });

    it('shows transaction source and source context', async () => {
        fetchTransactionMock.mockResolvedValue({
            id: 42,
            ledger_id: 7,
            settlement_id: null,
            payer_account_id: 10,
            payer_account_name: 'Alice Checking',
            destination_account_id: 20,
            destination_account_name: 'Household',
            amount: 1250,
            description: 'Groceries',
            date: '2026-09-19',
            type: 'recurring',
            source: 'blueprint',
            source_metadata: { recurring_transaction_id: 9 },
            split_rule: 'equal',
            postings: [
                {
                    id: 1,
                    account_id: 10,
                    account_name: 'Alice Checking',
                    amount: 1250,
                    direction: 'credit',
                },
                {
                    id: 2,
                    account_id: 20,
                    account_name: 'Household',
                    amount: 1250,
                    direction: 'debit',
                },
            ],
            created_at: '2026-09-19T12:00:00Z',
        });

        renderSheet();

        expect(await screen.findByText('Transaction #42')).toBeInTheDocument();
        expect(screen.getByText('Blueprint')).toBeInTheDocument();
        expect(screen.getByText('recurring transaction id')).toBeInTheDocument();
        expect(screen.getByText('9')).toBeInTheDocument();
        expect(screen.getAllByText('Alice Checking')).toHaveLength(2);
        expect(screen.getAllByText('Household')).toHaveLength(2);
        expect(screen.getByText('Account #10')).toBeInTheDocument();
        expect(screen.getByText('Account #20')).toBeInTheDocument();
        expect(fetchTransactionMock).toHaveBeenCalledWith(7, 42);
    });
});
