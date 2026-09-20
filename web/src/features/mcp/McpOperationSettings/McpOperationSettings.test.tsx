import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useLedgerStore } from '@/stores/ledgerStore';

import { McpOperationSettings } from './McpOperationSettings';

const fetchMcpSettingsMock = vi.fn();
const updateMcpSettingsMock = vi.fn();

vi.mock('@/api/mcp', () => ({
    fetchMcpSettings: (...args: unknown[]) => fetchMcpSettingsMock(...args),
    updateMcpSettings: (...args: unknown[]) => updateMcpSettingsMock(...args),
    fetchMcpTokens: () => Promise.resolve({
        data: [],
        meta: { mcp_url: 'http://localhost:8000/mcp/poruko', authorization_header: 'Authorization' },
    }),
    createMcpToken: vi.fn(),
    revokeMcpToken: vi.fn(),
    createMcpSignedUrl: vi.fn(),
}));

vi.mock('@/features/mcp/webmcp/getModelContext', () => ({
    getModelContext: () => null,
}));

function renderSettings() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: { retry: false },
            mutations: { retry: false },
        },
    });

    return render(
        <MemoryRouter>
            <QueryClientProvider client={queryClient}>
                <McpOperationSettings />
            </QueryClientProvider>
        </MemoryRouter>,
    );
}

describe('McpOperationSettings', () => {
    beforeEach(() => {
        fetchMcpSettingsMock.mockReset();
        updateMcpSettingsMock.mockReset();
        useLedgerStore.setState({ activeLedgerId: 7 });
        fetchMcpSettingsMock.mockResolvedValue({
            enabled: false,
            allow_read: true,
            allow_write: false,
            allow_destructive: false,
            post_mode: 'approval_queue',
        });
        updateMcpSettingsMock.mockResolvedValue({
            enabled: true,
            allow_read: true,
            allow_write: true,
            allow_destructive: false,
            post_mode: 'approval_queue',
        });
    });

    afterEach(() => {
        cleanup();
    });

    it('renders operation toggles and posting mode', async () => {
        renderSettings();

        expect(await screen.findByLabelText('Enable MCP for this space')).toBeInTheDocument();
        expect(screen.getByLabelText('Allow read operations')).toBeInTheDocument();
        expect(screen.getByLabelText('Allow write operations')).toBeInTheDocument();
        expect(screen.getByLabelText('Allow destructive operations')).toBeInTheDocument();
        expect(screen.getByLabelText('Write posting mode')).toBeInTheDocument();
        expect(screen.getByText('unsupported in this browser')).toBeInTheDocument();
    });

    it('requires destructive acknowledgement before enabling', async () => {
        const user = userEvent.setup();
        renderSettings();

        await screen.findByLabelText('Allow destructive operations');
        expect(screen.getByLabelText('Allow destructive operations')).toBeDisabled();

        await user.click(screen.getByLabelText('Acknowledge destructive MCP risks'));
        expect(screen.getByLabelText('Allow destructive operations')).toBeEnabled();
    });

    it('saves MCP settings via the API', async () => {
        const user = userEvent.setup();
        renderSettings();

        await screen.findByLabelText('Enable MCP for this space');
        await user.click(screen.getByLabelText('Enable MCP for this space'));
        await user.click(screen.getByRole('button', { name: 'Save MCP settings' }));

        await waitFor(() => {
            expect(updateMcpSettingsMock).toHaveBeenCalledWith(7, expect.objectContaining({
                enabled: true,
                allow_read: true,
                post_mode: 'approval_queue',
            }));
        });
    });
});
