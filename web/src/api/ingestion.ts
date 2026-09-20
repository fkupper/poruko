import client from '@/api/client';
import type {
    AiImportSettings,
    AiProvider,
    BankAccountMapping,
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
        provider: AiProvider;
        api_key?: string;
        model?: string;
        base_url?: string;
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
): Promise<StatementImport> {
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
