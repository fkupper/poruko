import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { McpKeysCard } from './McpKeysCard';

const fetchMcpTokensMock = vi.fn();
const createMcpTokenMock = vi.fn();
const revokeMcpTokenMock = vi.fn();
const createMcpSignedUrlMock = vi.fn();

vi.mock('@/api/mcp', () => ({
    fetchMcpTokens: (...args: unknown[]) => fetchMcpTokensMock(...args),
    createMcpToken: (...args: unknown[]) => createMcpTokenMock(...args),
    revokeMcpToken: (...args: unknown[]) => revokeMcpTokenMock(...args),
    createMcpSignedUrl: (...args: unknown[]) => createMcpSignedUrlMock(...args),
}));

function renderCard() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: { retry: false },
            mutations: { retry: false },
        },
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <McpKeysCard />
        </QueryClientProvider>,
    );
}

const createdResponse = {
    data: {
        id: 12,
        name: 'Cursor',
        last_used_at: null,
        created_at: '2026-09-20T18:00:00.000Z',
        expires_at: null,
    },
    meta: {
        token: '12|secret-mcp-key',
        mcp_url: 'http://localhost:8000/mcp/poruko',
        authorization_header: 'Authorization',
        client_config: {
            url: 'http://localhost:8000/mcp/poruko',
            headers: { Authorization: 'Bearer 12|secret-mcp-key' },
            cursor: {
                mcpServers: {
                    poruko: {
                        url: 'http://localhost:8000/mcp/poruko',
                        headers: { Authorization: 'Bearer 12|secret-mcp-key' },
                    },
                },
            },
            claude: {
                mcpServers: {
                    poruko: {
                        type: 'http' as const,
                        url: 'http://localhost:8000/mcp/poruko',
                        headers: { Authorization: 'Bearer 12|secret-mcp-key' },
                    },
                },
            },
        },
        signed_url: {
            url: 'http://localhost:8000/mcp/poruko?mcp_token=12&signature=abc',
            expires_at: '2026-09-21T18:00:00.000Z',
            expires_in_hours: 24,
        },
    },
};

describe('McpKeysCard', () => {
    beforeEach(() => {
        fetchMcpTokensMock.mockReset();
        createMcpTokenMock.mockReset();
        revokeMcpTokenMock.mockReset();
        createMcpSignedUrlMock.mockReset();
        fetchMcpTokensMock.mockResolvedValue({
            data: [],
            meta: {
                mcp_url: 'http://localhost:8000/mcp/poruko',
                authorization_header: 'Authorization',
            },
        });
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText: vi.fn().mockResolvedValue(undefined) },
        });
    });

    afterEach(() => {
        cleanup();
    });

    it('shows empty state and server URL', async () => {
        renderCard();

        expect(await screen.findByText('No MCP keys yet')).toBeInTheDocument();
        expect(screen.getByText('http://localhost:8000/mcp/poruko')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Create MCP key' })).toBeInTheDocument();
    });

    it('creates a key and shows copy-paste agent config once', async () => {
        const user = userEvent.setup();
        createMcpTokenMock.mockResolvedValue(createdResponse);
        renderCard();

        await user.click(await screen.findByRole('button', { name: 'Create MCP key' }));
        await user.clear(screen.getByLabelText('Key name'));
        await user.type(screen.getByLabelText('Key name'), 'Cursor');
        await user.click(screen.getByRole('button', { name: 'Create key' }));

        await waitFor(() => {
            expect(createMcpTokenMock).toHaveBeenCalledWith('Cursor');
        });

        expect(await screen.findByText('MCP key created')).toBeInTheDocument();
        expect(screen.getByLabelText('Authorization header')).toHaveValue('Bearer 12|secret-mcp-key');
        expect(screen.getByLabelText('Cursor MCP config')).toHaveValue(
            JSON.stringify(createdResponse.meta.client_config.cursor, null, 2),
        );
        expect(screen.getByLabelText('Signed URL (expires in 24h)')).toHaveValue(
            createdResponse.meta.signed_url.url,
        );

        await user.click(screen.getByRole('button', { name: 'Copy Cursor MCP config' }));
        expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
            JSON.stringify(createdResponse.meta.client_config.cursor, null, 2),
        );
    });

    it('revokes a listed key', async () => {
        const user = userEvent.setup();
        fetchMcpTokensMock.mockResolvedValue({
            data: [createdResponse.data],
            meta: {
                mcp_url: 'http://localhost:8000/mcp/poruko',
                authorization_header: 'Authorization',
            },
        });
        revokeMcpTokenMock.mockResolvedValue(undefined);
        renderCard();

        await user.click(await screen.findByRole('button', { name: 'Revoke' }));
        await user.click(screen.getByRole('button', { name: 'Revoke key' }));

        await waitFor(() => {
            expect(revokeMcpTokenMock).toHaveBeenCalledWith(12);
        });
    });
});
