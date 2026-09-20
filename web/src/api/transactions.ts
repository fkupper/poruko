import client from '@/api/client';
import type { CreateTransactionPayload, Transaction } from '@/api/types';

export interface FetchTransactionsFilters {
    from_date?: string;
    to_date?: string;
    account_id?: number;
    account_ids?: number[];
    creator_user_ids?: number[];
    split_rules?: string[];
    types?: string[];
    settlement_id?: number;
    per_page?: number;
    page?: number;
}

export async function fetchTransactions(ledgerId: number, filters?: FetchTransactionsFilters): Promise<Transaction[]> {
    const { data } = await client.get<{ data: Transaction[] }>(`/ledgers/${ledgerId}/transactions`, {
        params: filters,
    });
    return data.data;
}

export async function fetchTransaction(ledgerId: number, transactionId: number): Promise<Transaction> {
    const { data } = await client.get<{ data: Transaction }>(
        `/ledgers/${ledgerId}/transactions/${transactionId}`,
    );
    return data.data;
}

export async function createTransaction(ledgerId: number, payload: CreateTransactionPayload): Promise<Transaction> {
    const { data } = await client.post<{ data: Transaction }>(`/ledgers/${ledgerId}/transactions`, payload);
    return data.data;
}

export async function updateTransaction(ledgerId: number, transactionId: number, payload: CreateTransactionPayload): Promise<Transaction> {
    const { data } = await client.patch<{ data: Transaction }>(`/ledgers/${ledgerId}/transactions/${transactionId}`, payload);
    return data.data;
}

export async function deleteTransaction(ledgerId: number, transactionId: number): Promise<void> {
    await client.delete(`/ledgers/${ledgerId}/transactions/${transactionId}`);
}
