import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useLedgerStore } from '@/stores/ledgerStore';

import SettlementPage from './SettlementPage';

const fetchSettlementPreviewMock = vi.fn();
const fetchSettlementPeriodsMock = vi.fn();
const recordMidCycleSettlementTransferMock = vi.fn();

vi.mock('@/api/settlements', () => ({
    fetchSettlementPreview: (...args: unknown[]) => fetchSettlementPreviewMock(...args),
    fetchSettlementPeriods: (...args: unknown[]) => fetchSettlementPeriodsMock(...args),
    executeSettlement: vi.fn(),
    recordMidCycleSettlementTransfer: (...args: unknown[]) => recordMidCycleSettlementTransferMock(...args),
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
        recordMidCycleSettlementTransferMock.mockReset();
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

    it('suggests and records a transfer during an open cycle', async () => {
        const user = userEvent.setup();
        fetchSettlementPeriodsMock.mockResolvedValueOnce([]);
        fetchSettlementPreviewMock.mockResolvedValueOnce({
            period_start: '2026-09-01',
            period_end: '2026-09-30',
            settlement_mode: 'direct_p2p',
            is_settled: false,
            executed_at: null,
            summary: {
                total_shared_spend: 10_000,
                pool_base_budget: 0,
                pool_current_balance: 0,
            },
            required_transfers: [
                {
                    from_account_id: 22,
                    to_account_id: 11,
                    amount: 5_000,
                    instruction: 'Bob transfers to Alice',
                },
            ],
            user_breakdowns: [],
        });
        recordMidCycleSettlementTransferMock.mockResolvedValueOnce({ id: 91 });

        renderSettlementPage();

        expect(await screen.findByText('Bob transfers to Alice')).toBeInTheDocument();
        expect(screen.getByText('Available as a mid-cycle transfer')).toBeInTheDocument();
        expect(screen.getByText(/Suggested amount for the cycle ending 2026-09-30/i)).toBeInTheDocument();
        expect(screen.getByText(/reduces the final true-up/i)).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /record transfer now/i }));

        const dialog = screen.getByRole('dialog');
        expect(screen.getByRole('heading', { name: /record mid-cycle transfer/i })).toBeInTheDocument();
        expect(within(dialog).getByLabelText(/^amount$/i)).toHaveValue('50.00');
        expect(within(dialog).getByText(/Suggested remaining €50.00/i)).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /^record transfer$/i }));

        await waitFor(() => {
            expect(recordMidCycleSettlementTransferMock).toHaveBeenCalledWith(1, {
                period_end: '2026-09-30',
                from_account_id: 22,
                to_account_id: 11,
                amount: 5_000,
                idempotency_key: expect.any(String),
            });
        });
    });

    it('lets the user record a smaller amount than the suggestion', async () => {
        const user = userEvent.setup();
        fetchSettlementPeriodsMock.mockResolvedValueOnce([]);
        fetchSettlementPreviewMock.mockResolvedValueOnce({
            period_start: '2026-09-01',
            period_end: '2026-09-30',
            settlement_mode: 'direct_p2p',
            is_settled: false,
            executed_at: null,
            summary: {
                total_shared_spend: 10_000,
                pool_base_budget: 0,
                pool_current_balance: 0,
            },
            required_transfers: [
                {
                    from_account_id: 22,
                    to_account_id: 11,
                    amount: 5_000,
                    instruction: 'Bob transfers to Alice',
                },
            ],
            user_breakdowns: [],
        });
        recordMidCycleSettlementTransferMock.mockResolvedValueOnce({ id: 92 });

        renderSettlementPage();

        await user.click(await screen.findByRole('button', { name: /record transfer now/i }));

        const amountInput = screen.getByLabelText(/^amount$/i);
        await user.clear(amountInput);
        await user.type(amountInput, '20.00');

        await user.click(screen.getByRole('button', { name: /^record transfer$/i }));

        await waitFor(() => {
            expect(recordMidCycleSettlementTransferMock).toHaveBeenCalledWith(1, {
                period_end: '2026-09-30',
                from_account_id: 22,
                to_account_id: 11,
                amount: 2_000,
                idempotency_key: expect.any(String),
            });
        });
    });

    it('blocks recording more than the suggested remaining amount', async () => {
        const user = userEvent.setup();
        fetchSettlementPeriodsMock.mockResolvedValueOnce([]);
        fetchSettlementPreviewMock.mockResolvedValueOnce({
            period_start: '2026-09-01',
            period_end: '2026-09-30',
            settlement_mode: 'direct_p2p',
            is_settled: false,
            executed_at: null,
            summary: {
                total_shared_spend: 10_000,
                pool_base_budget: 0,
                pool_current_balance: 0,
            },
            required_transfers: [
                {
                    from_account_id: 22,
                    to_account_id: 11,
                    amount: 5_000,
                    instruction: 'Bob transfers to Alice',
                },
            ],
            user_breakdowns: [],
        });

        renderSettlementPage();

        await user.click(await screen.findByRole('button', { name: /record transfer now/i }));

        const amountInput = screen.getByLabelText(/^amount$/i);
        await user.clear(amountInput);
        await user.type(amountInput, '50.01');

        expect(screen.getByText(/Enter at most the suggested remaining amount of €50.00/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^record transfer$/i })).toBeDisabled();
        expect(recordMidCycleSettlementTransferMock).not.toHaveBeenCalled();
    });

    it('does not offer mid-cycle actions for a settled cycle', async () => {
        fetchSettlementPeriodsMock.mockResolvedValueOnce([]);
        fetchSettlementPreviewMock.mockResolvedValueOnce({
            period_start: '2026-08-01',
            period_end: '2026-08-31',
            settlement_mode: 'direct_p2p',
            is_settled: true,
            executed_at: '2026-09-01T12:00:00Z',
            summary: {
                total_shared_spend: 10_000,
                pool_base_budget: 0,
                pool_current_balance: 0,
            },
            required_transfers: [
                {
                    from_account_id: 22,
                    to_account_id: 11,
                    amount: 5_000,
                    instruction: 'Bob transfers to Alice',
                },
            ],
            user_breakdowns: [],
        });

        renderSettlementPage();

        expect(await screen.findByText('Bob transfers to Alice')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /record transfer now/i })).not.toBeInTheDocument();
        expect(screen.queryByText('Available as a mid-cycle transfer')).not.toBeInTheDocument();
        expect(screen.queryByText(/reduces the final true-up/i)).not.toBeInTheDocument();
    });
});
