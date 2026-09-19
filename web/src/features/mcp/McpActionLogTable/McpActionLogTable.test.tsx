import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useLedgerStore } from '@/stores/ledgerStore';

import { McpActionLogTable } from './McpActionLogTable';

const fetchMcpActionLogsMock = vi.fn();

vi.mock('@/api/mcp', () => ({
    fetchMcpActionLogs: (...args: unknown[]) => fetchMcpActionLogsMock(...args),
}));

function renderTable() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: { retry: false },
            mutations: { retry: false },
        },
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <McpActionLogTable />
        </QueryClientProvider>,
    );
}

describe('McpActionLogTable', () => {
    beforeEach(() => {
        fetchMcpActionLogsMock.mockReset();
        useLedgerStore.setState({ activeLedgerId: 7 });
    });

    afterEach(() => {
        cleanup();
    });

    it('renders an empty state', async () => {
        fetchMcpActionLogsMock.mockResolvedValue({ data: [] });
        renderTable();

        expect(await screen.findByText('No MCP actions yet')).toBeInTheDocument();
    });

    it('renders log rows', async () => {
        fetchMcpActionLogsMock.mockResolvedValue({
            data: [
                {
                    id: 1,
                    user_id: 3,
                    user_name: 'Ada',
                    ledger_id: 7,
                    tool_name: 'list-accounts',
                    operation: 'read',
                    request_payload: { ledger_id: 7 },
                    response_status: 'ok',
                    duration_ms: 12,
                    created_at: '2026-09-19T12:00:00.000Z',
                },
            ],
        });
        renderTable();

        expect(await screen.findByText('list-accounts')).toBeInTheDocument();
        expect(screen.getByText('ok')).toBeInTheDocument();
        expect(screen.getByText('12 ms')).toBeInTheDocument();
    });
});
