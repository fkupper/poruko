import client from '@/api/client';
import type { ApprovePendingItem, PendingTransaction } from '@/api/types';

export async function uploadBankStatement(
    ledgerId: number,
    file: File,
    targetAccountId: number
): Promise<{ message: string; job_id: string }> {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('target_account_id', targetAccountId.toString());

    const { data } = await client.post<{ message: string; job_id: string }>(
        `/ledgers/${ledgerId}/ingestion/upload`,
        formData,
        {
            headers: { 'Content-Type': 'multipart/form-data' },
        }
    );
    return data;
}

export async function fetchPendingTransactions(ledgerId: number): Promise<PendingTransaction[]> {
    const { data } = await client.get<{ data: PendingTransaction[] }>(`/ledgers/${ledgerId}/ingestion/pending`);
    return data.data;
}

export async function approvePendingTransactions(
    ledgerId: number,
    transactions: ApprovePendingItem[]
): Promise<void> {
    await client.post(`/ledgers/${ledgerId}/ingestion/approve`, { transactions });
}
