import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import McpActionLogPage from '@/pages/McpActionLogPage/McpActionLogPage';
import { useLedgerStore } from '@/stores/ledgerStore';

vi.mock('@/api/mcp', () => ({
    fetchPendingApprovalsA2ui: vi.fn().mockResolvedValue({
        protocol: 'a2ui',
        version: 'v0.9',
        surface_id: 'poruko.pending-approvals',
        messages: [],
    }),
}));

vi.mock('@/features/mcp/A2uiSurface', () => ({
    A2uiSurfaceRenderer: () => <div data-testid="a2ui-surface">Rendered A2UI approvals</div>,
}));

vi.mock('@/features/mcp/McpActionLogTable/McpActionLogTable', () => ({
    McpActionLogTable: () => <div data-testid="mcp-log">Action log</div>,
}));

describe('McpActionLogPage', () => {
    beforeEach(() => {
        useLedgerStore.setState({ activeLedgerId: 7 });
    });

    it('separates the official A2UI approval surface from observability logs', async () => {
        const queryClient = new QueryClient({
            defaultOptions: { queries: { retry: false } },
        });

        render(
            <QueryClientProvider client={queryClient}>
                <MemoryRouter>
                    <McpActionLogPage />
                </MemoryRouter>
            </QueryClientProvider>,
        );

        expect(await screen.findByTestId('a2ui-surface')).toHaveTextContent('Rendered A2UI approvals');
        expect(screen.getByTestId('mcp-log')).toHaveTextContent('Action log');
        expect(screen.getByText('A2UI v0.9')).toBeInTheDocument();
        expect(screen.getByText(/This is not the pending-transaction approval queue/)).toBeInTheDocument();
    });
});
