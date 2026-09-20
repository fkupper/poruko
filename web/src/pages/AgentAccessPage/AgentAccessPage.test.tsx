import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import AgentAccessPage from '@/pages/AgentAccessPage/AgentAccessPage';
import { useLedgerStore } from '@/stores/ledgerStore';

const updateMcpSettings = vi.fn();

vi.mock('@/api/mcp', () => ({
    fetchMcpSettings: vi.fn().mockResolvedValue({
        read_enabled: true,
        write_enabled: false,
        destructive_enabled: false,
        require_transaction_approval: true,
        endpoint: 'https://poruko.test/mcp/poruko',
    }),
    updateMcpSettings: (...arguments_: unknown[]) => updateMcpSettings(...arguments_),
    fetchMcpTools: vi.fn().mockResolvedValue([
        {
            name: 'list-accounts',
            title: 'List Accounts',
            description: 'List accounts.',
            operation: 'read',
            input_schema: { type: 'object' },
            annotations: { readOnlyHint: true },
        },
        {
            name: 'create-transaction',
            title: 'Create Transaction',
            description: 'Create a transaction.',
            operation: 'write',
            input_schema: { type: 'object' },
            annotations: { readOnlyHint: false },
        },
    ]),
    fetchMcpActionLogs: vi.fn().mockResolvedValue([
        {
            id: 9,
            ledger_id: 4,
            ledger_name: 'Home',
            tool: 'create-transaction',
            operation: 'write',
            transport: 'web_mcp',
            status: 'succeeded',
            arguments: {},
            result_summary: {},
            error_message: null,
            duration_ms: 14,
            started_at: '2026-09-19T20:00:00Z',
            completed_at: '2026-09-19T20:00:00Z',
        },
    ]),
    fetchA2uiFinanceSummary: vi.fn().mockResolvedValue({
        protocol: 'a2ui',
        version: 'v0.9',
        surface_id: 'poruko-finance-4',
        messages: [],
        data: { profile: null, settlement_preview: {} },
    }),
}));

vi.mock('@/hooks/use-web-mcp-tools', () => ({
    useWebMcpTools: () => ({
        status: 'ready',
        registeredCount: 2,
        error: null,
    }),
}));

vi.mock('@/features/mcp/A2uiFinanceSurface', () => ({
    A2uiFinanceSurface: () => <div data-testid="a2ui-surface">Rendered A2UI</div>,
}));

function renderPage() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: { retry: false },
            mutations: { retry: false },
        },
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <AgentAccessPage />
        </QueryClientProvider>,
    );
}

describe('AgentAccessPage', () => {
    afterEach(() => cleanup());

    beforeEach(() => {
        updateMcpSettings.mockReset();
        updateMcpSettings.mockImplementation(async (settings) => ({
            ...settings,
            endpoint: 'https://poruko.test/mcp/poruko',
        }));
        useLedgerStore.setState({ activeLedgerId: 4 });
    });

    it('shows WebMCP, A2UI, and private action-log state', async () => {
        renderPage();

        expect(await screen.findByRole('heading', { name: 'Agent access' })).toBeInTheDocument();
        expect(await screen.findByText('2 policy-enabled tools are exposed to in-page agents.')).toBeInTheDocument();
        expect(screen.getByTestId('a2ui-surface')).toHaveTextContent('Rendered A2UI');
        expect((await screen.findAllByText('create-transaction')).length).toBeGreaterThan(0);
        expect(screen.getByText('web_mcp')).toBeInTheDocument();
        expect(screen.getByText('14 ms')).toBeInTheDocument();
    });

    it('saves independent write and destructive settings', async () => {
        const user = userEvent.setup();
        renderPage();

        const write = await screen.findByRole('switch', { name: 'Write operations' });
        const destructive = screen.getByRole('switch', { name: 'Destructive operations' });
        await user.click(write);
        await user.click(destructive);
        await user.click(screen.getAllByRole('button', { name: 'Save permissions' }).at(-1)!);

        await waitFor(() => expect(updateMcpSettings).toHaveBeenCalled());
        expect(updateMcpSettings.mock.calls[0][0]).toEqual({
            read_enabled: true,
            write_enabled: true,
            destructive_enabled: true,
            require_transaction_approval: true,
        });
    });
});
