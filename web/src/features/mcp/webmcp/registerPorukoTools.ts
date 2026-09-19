import { fetchAccounts } from '@/api/accounts';
import { fetchFinancialProfile } from '@/api/finances';
import { fetchMcpActionLogs, fetchMcpSettings, type McpSettings } from '@/api/mcp';
import { createPendingTransaction, fetchPendingTransactions } from '@/api/pending-transactions';
import { fetchRecurringBlueprints } from '@/api/recurring';
import { fetchSettlementPreview } from '@/api/settlements';
import { fetchTransaction, fetchTransactions } from '@/api/transactions';
import { useAuthStore } from '@/stores/authStore';
import { useLedgerStore } from '@/stores/ledgerStore';

import { getModelContext, type ModelContextLike } from './getModelContext';

function requireLedgerId(): number {
    const ledgerId = useLedgerStore.getState().activeLedgerId;
    if (ledgerId === null) {
        throw new Error('Select a space before using MCP tools.');
    }
    return ledgerId;
}

function requireUserId(): number {
    const userId = useAuthStore.getState().user?.id;
    if (!userId) {
        throw new Error('You must be signed in to use MCP tools.');
    }
    return userId;
}

async function requireCapability(
    settings: McpSettings,
    operation: 'read' | 'write' | 'destructive',
): Promise<void> {
    if (!settings.enabled) {
        throw new Error('MCP access is disabled for this space. Enable it in Space Settings.');
    }

    if (operation === 'read' && !settings.allow_read) {
        throw new Error('MCP read operations are disabled for this space.');
    }

    if (operation === 'write' && !settings.allow_write) {
        throw new Error('MCP write operations are disabled for this space.');
    }

    if (operation === 'destructive' && !settings.allow_destructive) {
        throw new Error('MCP destructive operations are disabled for this space.');
    }
}

export function registerPorukoTools(modelContext: ModelContextLike, signal: AbortSignal): void {
    modelContext.registerTool({
        name: 'poruko.list_accounts',
        description: 'List accounts in the active Poruko space.',
        readOnlyHint: true,
        signal,
        inputSchema: { type: 'object', properties: {} },
        execute: async () => {
            const ledgerId = requireLedgerId();
            await requireCapability(await fetchMcpSettings(ledgerId), 'read');
            return fetchAccounts(ledgerId);
        },
    });

    modelContext.registerTool({
        name: 'poruko.list_transactions',
        description: 'List posted transactions in the active Poruko space.',
        readOnlyHint: true,
        signal,
        inputSchema: { type: 'object', properties: {} },
        execute: async () => {
            const ledgerId = requireLedgerId();
            await requireCapability(await fetchMcpSettings(ledgerId), 'read');
            return fetchTransactions(ledgerId);
        },
    });

    modelContext.registerTool({
        name: 'poruko.show_transaction',
        description: 'Show one posted transaction including source provenance.',
        readOnlyHint: true,
        signal,
        inputSchema: {
            type: 'object',
            properties: { transaction_id: { type: 'number' } },
            required: ['transaction_id'],
        },
        execute: async (input) => {
            const ledgerId = requireLedgerId();
            await requireCapability(await fetchMcpSettings(ledgerId), 'read');
            return fetchTransaction(ledgerId, Number(input.transaction_id));
        },
    });

    modelContext.registerTool({
        name: 'poruko.list_pending',
        description: 'List pending approval proposals. This is not the MCP action log.',
        readOnlyHint: true,
        signal,
        inputSchema: { type: 'object', properties: {} },
        execute: async () => {
            const ledgerId = requireLedgerId();
            await requireCapability(await fetchMcpSettings(ledgerId), 'read');
            return fetchPendingTransactions(ledgerId);
        },
    });

    modelContext.registerTool({
        name: 'poruko.propose_transaction',
        description: 'Submit a transaction to the shared pending approval queue with source MCP.',
        signal,
        inputSchema: {
            type: 'object',
            properties: {
                payer_account_id: { type: 'number' },
                destination_account_id: { type: 'number' },
                amount: { type: 'number' },
                description: { type: 'string' },
                date: { type: 'string' },
                split_rule: { type: 'string' },
            },
            required: ['payer_account_id', 'destination_account_id', 'amount', 'date', 'split_rule'],
        },
        execute: async (input) => {
            const ledgerId = requireLedgerId();
            await requireCapability(await fetchMcpSettings(ledgerId), 'write');
            return createPendingTransaction(ledgerId, {
                payer_account_id: Number(input.payer_account_id),
                destination_account_id: Number(input.destination_account_id),
                amount: Number(input.amount),
                description: typeof input.description === 'string' ? input.description : undefined,
                date: String(input.date),
                split_rule: String(input.split_rule),
            });
        },
    });

    modelContext.registerTool({
        name: 'poruko.list_recurring',
        description: 'List recurring blueprints in the active space.',
        readOnlyHint: true,
        signal,
        inputSchema: { type: 'object', properties: {} },
        execute: async () => {
            const ledgerId = requireLedgerId();
            await requireCapability(await fetchMcpSettings(ledgerId), 'read');
            return fetchRecurringBlueprints(ledgerId);
        },
    });

    modelContext.registerTool({
        name: 'poruko.get_financial_profile',
        description: 'Get the authenticated user My Finance profile.',
        readOnlyHint: true,
        signal,
        inputSchema: { type: 'object', properties: {} },
        execute: async () => {
            const ledgerId = requireLedgerId();
            await requireCapability(await fetchMcpSettings(ledgerId), 'read');
            return fetchFinancialProfile(ledgerId, requireUserId());
        },
    });

    modelContext.registerTool({
        name: 'poruko.preview_settlement',
        description: 'Preview the next settlement period.',
        readOnlyHint: true,
        signal,
        inputSchema: { type: 'object', properties: {} },
        execute: async () => {
            const ledgerId = requireLedgerId();
            await requireCapability(await fetchMcpSettings(ledgerId), 'read');
            return fetchSettlementPreview(ledgerId);
        },
    });

    modelContext.registerTool({
        name: 'poruko.list_mcp_action_logs',
        description: 'List MCP action logs for observability. Separate from pending approvals.',
        readOnlyHint: true,
        signal,
        inputSchema: { type: 'object', properties: {} },
        execute: async () => {
            const ledgerId = requireLedgerId();
            await requireCapability(await fetchMcpSettings(ledgerId), 'read');
            return fetchMcpActionLogs(ledgerId);
        },
    });
}
