import client from '@/api/client';
import type {
    ParticipantShare,
    PendingTransaction,
    SplitRule,
    Transaction,
} from '@/api/types';

export async function fetchPendingTransactions(ledgerId: number): Promise<PendingTransaction[]> {
    const { data } = await client.get<{ data: PendingTransaction[] }>(
        `/ledgers/${ledgerId}/pending-transactions`,
    );

    return data.data;
}

export async function approvePendingTransaction(
    ledgerId: number,
    pendingTransactionId: number,
): Promise<Transaction> {
    const { data } = await client.post<{ data: Transaction }>(
        `/ledgers/${ledgerId}/pending-transactions/${pendingTransactionId}/approve`,
    );

    return data.data;
}

export async function updatePendingTransaction(
    ledgerId: number,
    pendingTransactionId: number,
    payload: {
        payer_account_id?: number | null;
        destination_account_id?: number | null;
        description?: string | null;
        amount?: number | null;
        date?: string | null;
        split_rule?: SplitRule | null;
        participants?: ParticipantShare[];
    },
): Promise<PendingTransaction> {
    const { data } = await client.patch<{ data: PendingTransaction }>(
        `/ledgers/${ledgerId}/pending-transactions/${pendingTransactionId}`,
        payload,
    );

    return data.data;
}

export async function approvePendingTransactions(
    ledgerId: number,
    pendingTransactionIds: number[],
): Promise<Transaction[]> {
    const { data } = await client.post<{ data: Transaction[] }>(
        `/ledgers/${ledgerId}/pending-transactions/approve-batch`,
        { pending_transaction_ids: pendingTransactionIds },
    );

    return data.data;
}

export async function rejectPendingTransaction(
    ledgerId: number,
    pendingTransactionId: number,
    reason?: string,
): Promise<PendingTransaction> {
    const { data } = await client.post<{ data: PendingTransaction }>(
        `/ledgers/${ledgerId}/pending-transactions/${pendingTransactionId}/reject`,
        { reason },
    );

    return data.data;
}

export async function rejectPendingTransactions(
    ledgerId: number,
    pendingTransactionIds: number[],
    reason?: string,
): Promise<PendingTransaction[]> {
    const { data } = await client.post<{ data: PendingTransaction[] }>(
        `/ledgers/${ledgerId}/pending-transactions/reject-batch`,
        { pending_transaction_ids: pendingTransactionIds, reason },
    );

    return data.data;
}
