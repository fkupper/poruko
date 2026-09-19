import client from '@/api/client';
import type {
    ApprovePendingItem,
    AiImportSettings,
    BankAccountMapping,
    PendingTransaction,
    StatementImport,
} from '@/api/types';

export async function fetchAiImportSettings(ledgerId: number): Promise<AiImportSettings> {
    const { data } = await client.get<{ data: AiImportSettings }>(
        `/ledgers/${ledgerId}/ai-import/settings`,
    );

    return data.data;
}

export async function saveAiImportSettings(
    ledgerId: number,
    payload: {
        provider: 'openai' | 'anthropic';
        api_key?: string;
        model?: string;
        auto_create_accounts: boolean;
    },
): Promise<AiImportSettings> {
    const { data } = await client.put<{ data: AiImportSettings }>(
        `/ledgers/${ledgerId}/ai-import/settings`,
        payload,
    );

    return data.data;
}

export async function deleteAiImportSettings(ledgerId: number): Promise<void> {
    await client.delete(`/ledgers/${ledgerId}/ai-import/settings`);
}

export async function uploadBankStatement(
    ledgerId: number,
    file: File,
    _legacyTargetAccountId?: number,
): Promise<StatementImport & { message?: string }> {
    void _legacyTargetAccountId;
    const formData = new FormData();
    formData.append('statement', file);

    const { data } = await client.post<{ data: StatementImport }>(
        `/ledgers/${ledgerId}/ai-import/statements`,
        formData,
        {
            headers: { 'Content-Type': 'multipart/form-data' },
        },
    );

    return data.data;
}

/**
 * @deprecated The transaction page owns the shared approval queue.
 */
export async function fetchPendingTransactions(ledgerId: number): Promise<PendingTransaction[]> {
    const { data } = await client.get<{ data: PendingTransaction[] }>(
        `/ledgers/${ledgerId}/pending-transactions`,
    );

    return data.data.filter((transaction) => transaction.source === 'ai_import');
}

/**
 * @deprecated Use the shared pending-transactions API.
 */
export async function approvePendingTransactions(
    ledgerId: number,
    transactions: ApprovePendingItem[],
): Promise<void> {
    await client.post(`/ledgers/${ledgerId}/pending-transactions/approve-batch`, {
        pending_transaction_ids: transactions.map((transaction) => transaction.pending_transaction_id),
    });
}

export async function fetchStatementImports(ledgerId: number): Promise<StatementImport[]> {
    const { data } = await client.get<{ data: StatementImport[] }>(
        `/ledgers/${ledgerId}/ai-import/statements`,
    );

    return data.data;
}

export async function fetchBankAccountMappings(
    ledgerId: number,
): Promise<BankAccountMapping[]> {
    const { data } = await client.get<{ data: BankAccountMapping[] }>(
        `/ledgers/${ledgerId}/ai-import/mappings`,
    );

    return data.data;
}

export async function updateBankAccountMapping(
    ledgerId: number,
    mappingId: number,
    accountId: number | null,
): Promise<BankAccountMapping> {
    const { data } = await client.patch<{ data: BankAccountMapping }>(
        `/ledgers/${ledgerId}/ai-import/mappings/${mappingId}`,
        { account_id: accountId },
    );

    return data.data;
}
