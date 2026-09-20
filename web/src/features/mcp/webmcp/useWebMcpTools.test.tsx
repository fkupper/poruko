import { cleanup, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { useWebMcpTools } from './useWebMcpTools';

const registerTool = vi.fn();

vi.mock('./getModelContext', () => ({
    getModelContext: () => ({ registerTool }),
}));

vi.mock('./registerPorukoTools', () => ({
    registerPorukoTools: (modelContext: { registerTool: typeof registerTool }, signal: AbortSignal) => {
        modelContext.registerTool({
            name: 'poruko.list_accounts',
            description: 'List accounts',
            inputSchema: {},
            execute: async () => [],
            signal,
        });
    },
}));

describe('useWebMcpTools', () => {
    afterEach(() => {
        cleanup();
        registerTool.mockReset();
    });

    it('registers tools when a ledger is active', () => {
        const { unmount } = renderHook(() => useWebMcpTools(7));
        expect(registerTool).toHaveBeenCalled();
        unmount();
    });

    it('does not register when no ledger is selected', () => {
        renderHook(() => useWebMcpTools(null));
        expect(registerTool).not.toHaveBeenCalled();
    });
});
