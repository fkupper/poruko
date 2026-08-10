import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { Ledger } from '@/api/types';
import { useLedgerStore } from '@/stores/ledgerStore';

import { SpaceGuard } from './SpaceGuard';

const fetchLedgersMock = vi.fn();

vi.mock('@/api/ledgers', () => ({
    fetchLedgers: () => fetchLedgersMock(),
}));

function createTestQueryClient() {
    return new QueryClient({
        defaultOptions: {
            queries: { retry: false },
            mutations: { retry: false },
        },
    });
}

const spaceA: Ledger = {
    id: 1,
    name: 'Alpha',
    currency: 'EUR',
    settlement_mode: 'direct_p2p',
    settlement_timezone: 'UTC',
    settlement_cutoff_day: 1,
    settlement_cutoff_time: '00:00',
    settlement_auto_execute_enabled: false,
    created_at: null,
    updated_at: null,
};

function renderGuard(initialPath = '/') {
    const queryClient = createTestQueryClient();
    return render(
        <QueryClientProvider client={queryClient}>
            <MemoryRouter initialEntries={[initialPath]}>
                <Routes>
                    <Route element={<SpaceGuard />}>
                        <Route path="/" element={<div>Dashboard outlet</div>} />
                        <Route path="/setup" element={<div>Setup page</div>} />
                        <Route path="/accounts" element={<div>Accounts outlet</div>} />
                    </Route>
                </Routes>
            </MemoryRouter>
        </QueryClientProvider>,
    );
}

describe('SpaceGuard', () => {
    beforeEach(() => {
        fetchLedgersMock.mockReset();
        localStorage.clear();
        useLedgerStore.setState({ activeLedgerId: null });
    });

    afterEach(() => {
        cleanup();
    });

    it('redirects to /setup when the user has no spaces', async () => {
        fetchLedgersMock.mockResolvedValueOnce([]);

        renderGuard('/accounts');

        expect(await screen.findByText('Setup page')).toBeInTheDocument();
        expect(screen.queryByText('Accounts outlet')).not.toBeInTheDocument();
    });

    it('allows the outlet when spaces exist', async () => {
        fetchLedgersMock.mockResolvedValueOnce([spaceA]);

        renderGuard('/');

        expect(await screen.findByText('Dashboard outlet')).toBeInTheDocument();
    });

    it('bootstraps activeLedgerId when it is null', async () => {
        fetchLedgersMock.mockResolvedValue([spaceA]);
        expect(useLedgerStore.getState().activeLedgerId).toBeNull();

        renderGuard('/');

        expect(await screen.findByText('Dashboard outlet')).toBeInTheDocument();
        await waitFor(() => {
            expect(useLedgerStore.getState().activeLedgerId).toBe(1);
        });
    });
});
