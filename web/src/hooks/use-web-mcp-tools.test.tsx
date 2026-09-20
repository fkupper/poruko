import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import type { McpToolDefinition } from '@/api/mcp';
import { useWebMcpTools } from '@/hooks/use-web-mcp-tools';

const executeMcpTool = vi.fn();

vi.mock('@/api/mcp', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@/api/mcp')>();

    return {
        ...actual,
        executeMcpTool: (...arguments_: unknown[]) => executeMcpTool(...arguments_),
    };
});

const tools: McpToolDefinition[] = [{
    name: 'list-accounts',
    title: 'List Accounts',
    description: 'List policy-visible accounts.',
    operation: 'read',
    input_schema: {
        type: 'object',
        properties: { ledger_id: { type: 'integer' } },
        required: ['ledger_id'],
    },
    annotations: { readOnlyHint: true },
}];

afterEach(() => {
    Reflect.deleteProperty(document, 'modelContext');
    executeMcpTool.mockReset();
});

describe('useWebMcpTools', () => {
    it('reports unsupported browsers without registering tools', () => {
        const { result } = renderHook(() => useWebMcpTools(tools));

        expect(result.current).toEqual({
            status: 'unsupported',
            registeredCount: 0,
            error: null,
        });
    });

    it('registers policy-enabled tools and executes through the shared API', async () => {
        let registrationSignal: AbortSignal | undefined;
        const registerTool = vi.fn(async (_tool: ModelContextTool, options?: { signal?: AbortSignal }) => {
            registrationSignal = options?.signal;
            return undefined;
        });
        Object.defineProperty(document, 'modelContext', {
            configurable: true,
            value: {
                registerTool,
            },
        });
        executeMcpTool.mockResolvedValue({ accounts: [] });

        const { result, unmount } = renderHook(() => useWebMcpTools(tools));

        await waitFor(() => expect(result.current.status).toBe('ready'));
        const registeredTool = registerTool.mock.calls[0][0];
        expect(result.current.registeredCount).toBe(1);
        expect(registeredTool.name).toBe('list-accounts');
        expect(registeredTool.annotations?.readOnlyHint).toBe(true);

        await act(async () => {
            await expect(registeredTool.execute(
                { ledger_id: 4 },
                { signal: new AbortController().signal },
            )).resolves.toBe('{"accounts":[]}');
        });
        expect(executeMcpTool).toHaveBeenCalledWith('list-accounts', { ledger_id: 4 });

        unmount();
        expect(registrationSignal?.aborted).toBe(true);
    });
});
