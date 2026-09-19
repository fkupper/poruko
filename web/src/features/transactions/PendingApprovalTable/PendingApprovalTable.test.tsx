import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { PendingTransaction } from '@/api/types';
import { useLedgerStore } from '@/stores/ledgerStore';

import { PendingApprovalTable } from './PendingApprovalTable';

const fetchPendingTransactionsMock = vi.fn();
const approvePendingTransactionMock = vi.fn();
const approvePendingTransactionsMock = vi.fn();
const rejectPendingTransactionMock = vi.fn();
const rejectPendingTransactionsMock = vi.fn();
const updatePendingTransactionMock = vi.fn();
const fetchAccountsMock = vi.fn();
const createAccountMock = vi.fn();
const updateBankAccountMappingMock = vi.fn();

vi.mock('@/api/pending-transactions', () => ({
    fetchPendingTransactions: (...args: unknown[]) => fetchPendingTransactionsMock(...args),
    approvePendingTransaction: (...args: unknown[]) => approvePendingTransactionMock(...args),
    approvePendingTransactions: (...args: unknown[]) => approvePendingTransactionsMock(...args),
    rejectPendingTransaction: (...args: unknown[]) => rejectPendingTransactionMock(...args),
    rejectPendingTransactions: (...args: unknown[]) => rejectPendingTransactionsMock(...args),
    updatePendingTransaction: (...args: unknown[]) => updatePendingTransactionMock(...args),
}));

vi.mock('@/api/accounts', () => ({
    fetchAccounts: (...args: unknown[]) => fetchAccountsMock(...args),
    createAccount: (...args: unknown[]) => createAccountMock(...args),
}));

vi.mock('@/api/ingestion', () => ({
    updateBankAccountMapping: (...args: unknown[]) => updateBankAccountMappingMock(...args),
}));

vi.mock('@/hooks/use-ledger-currency', () => ({
    useLedgerCurrencySymbol: () => '€',
}));

const proposals: PendingTransaction[] = [
    {
        id: 10,
        ledger_id: 7,
        proposed_by_user_id: 1,
        proposed_by_user_name: 'Alice',
        payer_account_id: 100,
        payer_account_name: 'Alice Checking',
        destination_account_id: 200,
        destination_account_name: 'Household',
        raw_data: { description: 'MARKET' },
        raw_description: 'MARKET',
        suggested_description: 'Groceries',
        suggested_amount: 2500,
        suggested_split_rule: 'equal',
        suggested_participants: [],
        date: '2026-09-19',
        source: 'ai_import',
        status: 'pending',
        confidence: 0.95,
    },
    {
        id: 11,
        ledger_id: 7,
        proposed_by_user_id: 1,
        proposed_by_user_name: 'Alice',
        payer_account_id: 100,
        payer_account_name: 'Alice Checking',
        destination_account_id: 200,
        destination_account_name: 'Household',
        raw_data: { description: 'POWER' },
        raw_description: 'POWER',
        suggested_description: 'Electricity',
        suggested_amount: 6000,
        suggested_split_rule: 'proportional',
        suggested_participants: [],
        date: '2026-09-18',
        source: 'mcp',
        status: 'pending',
        confidence: 0.87,
    },
];

function renderTable() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: { retry: false },
            mutations: { retry: false },
        },
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <PendingApprovalTable />
        </QueryClientProvider>,
    );
}

describe('PendingApprovalTable', () => {
    beforeEach(() => {
        fetchPendingTransactionsMock.mockReset();
        approvePendingTransactionMock.mockReset();
        approvePendingTransactionsMock.mockReset();
        rejectPendingTransactionMock.mockReset();
        rejectPendingTransactionsMock.mockReset();
        updatePendingTransactionMock.mockReset();
        fetchAccountsMock.mockReset();
        createAccountMock.mockReset();
        updateBankAccountMappingMock.mockReset();
        useLedgerStore.setState({ activeLedgerId: 7 });

        fetchPendingTransactionsMock.mockResolvedValue(proposals);
        approvePendingTransactionMock.mockResolvedValue({ id: 20 });
        approvePendingTransactionsMock.mockResolvedValue([{ id: 20 }, { id: 21 }]);
        rejectPendingTransactionMock.mockResolvedValue({ ...proposals[0], status: 'rejected' });
        rejectPendingTransactionsMock.mockResolvedValue(
            proposals.map((proposal) => ({ ...proposal, status: 'rejected' })),
        );
        updatePendingTransactionMock.mockResolvedValue(proposals[0]);
        fetchAccountsMock.mockResolvedValue([
            {
                id: 100,
                ledger_id: 7,
                owner_id: 1,
                name: 'Alice Checking',
                type: 'user_funding',
                balance: 0,
            },
            {
                id: 200,
                ledger_id: 7,
                owner_id: null,
                name: 'Household',
                type: 'space_expense',
                balance: 0,
            },
        ]);
        createAccountMock.mockResolvedValue({
            id: 300,
            ledger_id: 7,
            owner_id: 1,
            name: 'Imported checking',
            type: 'user_funding',
            balance: 0,
        });
        updateBankAccountMappingMock.mockResolvedValue({});
    });

    afterEach(() => {
        cleanup();
    });

    it('approves a proposal individually', async () => {
        const user = userEvent.setup();
        renderTable();

        await screen.findByText('Groceries');
        expect(screen.getByText('AI Import')).toBeInTheDocument();
        expect(screen.getByText('MCP')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Approve Groceries' }));

        await waitFor(() => {
            expect(approvePendingTransactionMock).toHaveBeenCalledWith(7, 10);
        });
    });

    it('approves selected proposals as a batch', async () => {
        const user = userEvent.setup();
        renderTable();

        await screen.findByText('Groceries');
        await user.click(screen.getByLabelText('Select all pending transactions'));
        await user.click(screen.getByRole('button', { name: 'Approve selected' }));

        await waitFor(() => {
            expect(approvePendingTransactionsMock).toHaveBeenCalledWith(7, [10, 11]);
        });
    });

    it('confirms rejection before reviewing a proposal', async () => {
        const user = userEvent.setup();
        renderTable();

        await screen.findByText('Groceries');
        await user.click(screen.getByRole('button', { name: 'Reject Groceries' }));
        await user.click(screen.getByRole('button', { name: 'Reject proposal' }));

        await waitFor(() => {
            expect(rejectPendingTransactionMock).toHaveBeenCalledWith(7, 10);
        });
    });

    it('creates and associates a Poruko account while reviewing an import', async () => {
        const user = userEvent.setup();
        fetchPendingTransactionsMock.mockResolvedValue([
            {
                ...proposals[0],
                payer_account_id: null,
                payer_account_name: null,
                raw_data: {
                    description: 'MARKET',
                    bank_account_mapping_id: 44,
                    bank_account_name: 'Imported checking',
                    ownership: 'personal',
                },
            },
        ]);
        renderTable();

        await screen.findByText('Groceries');
        await user.click(screen.getByRole('button', { name: 'Review Groceries' }));
        await user.click(screen.getByRole('button', { name: 'Create' }));

        await waitFor(() => {
            expect(createAccountMock).toHaveBeenCalledWith(7, {
                name: 'Imported checking',
                type: 'user_funding',
            });
            expect(updateBankAccountMappingMock).toHaveBeenCalledWith(7, 44, 300);
        });

        await user.click(screen.getByRole('button', { name: 'Save review details' }));
        await waitFor(() => {
            expect(updatePendingTransactionMock).toHaveBeenCalledWith(7, 10, {
                payer_account_id: 300,
                destination_account_id: 200,
            });
        });
    });
});
