import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useLedgerStore } from '@/stores/ledgerStore';

import SettlementPage from './SettlementPage';

const fetchSettlementPreviewMock = vi.fn();
const fetchSettlementPeriodsMock = vi.fn();

vi.mock('@/api/settlements', () => ({
    fetchSettlementPreview: (...args: unknown[]) => fetchSettlementPreviewMock(...args),
    fetchSettlementPeriods: (...args: unknown[]) => fetchSettlementPeriodsMock(...args),
    executeSettlement: vi.fn(),
}));

vi.mock('@/hooks/use-ledger-currency', () => ({
    useLedgerCurrencySymbol: () => '€',
}));

vi.mock('@/features/transactions/TransactionsTable/TransactionsTable', () => ({
    TransactionsTable: () => <div>Transactions table</div>,
}));

function createTestQueryClient() {
    return new QueryClient({
        defaultOptions: {
            queries: { retry: false },
            mutations: { retry: false },
        },
    });
}

function renderSettlementPage() {
    const queryClient = createTestQueryClient();
    return render(
        <QueryClientProvider client={queryClient}>
            <MemoryRouter>
                <SettlementPage />
            </MemoryRouter>
        </QueryClientProvider>,
    );
}

describe('SettlementPage', () => {
    beforeEach(() => {
        fetchSettlementPreviewMock.mockReset();
        fetchSettlementPeriodsMock.mockReset();
        useLedgerStore.setState({ activeLedgerId: 1 });
    });

    afterEach(() => {
        cleanup();
    });

    it('shows loading skeletons while preview is pending', () => {
        fetchSettlementPeriodsMock.mockReturnValue(new Promise(() => undefined));
        fetchSettlementPreviewMock.mockReturnValue(new Promise(() => undefined));

        renderSettlementPage();

        expect(screen.getByText(/End-of-Month Settlement Engine/i)).toBeInTheDocument();
        expect(document.querySelectorAll('[data-slot="skeleton"]').length).toBeGreaterThan(0);
    });

    it('shows balanced empty state when there are no required transfers', async () => {
        fetchSettlementPeriodsMock.mockResolvedValueOnce([]);
        fetchSettlementPreviewMock.mockResolvedValueOnce({
            period_start: '2026-07-01',
            period_end: '2026-07-31',
            settlement_mode: 'direct_p2p',
            is_settled: false,
            executed_at: null,
            summary: {
                total_shared_spend: 0,
                pool_base_budget: 0,
                pool_current_balance: 0,
            },
            required_transfers: [],
            user_breakdowns: [],
        });

        renderSettlementPage();

        await waitFor(() => {
            expect(screen.getByText(/All members are completely balanced/i)).toBeInTheDocument();
        });
    });
});
